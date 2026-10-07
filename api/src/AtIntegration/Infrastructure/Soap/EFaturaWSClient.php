<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Soap;

use App\AtIntegration\Domain\Efatura\EFaturaRequestBuilder;
use App\AtIntegration\Domain\Webservice\AtCommunicationOutcome;
use App\AtIntegration\Domain\Webservice\AtDocumentWebserviceClient;
use App\Shared\Domain\AtIntegration\AtCommunicableDocument;
use App\Shared\Domain\Company\AtCredentialsNotConfigured;
use App\Shared\Domain\Company\AtCredentialsProvider;
use App\Shared\Domain\CompanyId;

/**
 * `Fatcorews.wsdl`'s `RegisterInvoice`/`RegisterWork`/`ChangeWorkStatus`
 * over hand-built SOAP 1.1 envelopes (document/literal, empty `SOAPAction` —
 * the WSDL's own binding) instead of `ext-soap`'s `SoapClient`, which task
 * 3.1 used for the series service. Deviation from docs/plans/phase-3.md
 * decision 4, deliberate: the body is built by {@see EFaturaRequestBuilder}
 * and unit-tested against the XSD embedded in the WSDL itself, and this
 * class's request/response handling runs in the plain test suite through a
 * fake {@see AtSoapHttpTransport} — none of which `SoapClient`'s WSDL-driven
 * array serialisation would allow without `ext-soap` and a live AT.
 *
 * The response is read by local element name (`CodigoResposta`, `Mensagem`),
 * independent of the prefixes AT happens to use.
 */
final class EFaturaWSClient implements AtDocumentWebserviceClient
{
    private const SOAP_ENVELOPE_NAMESPACE = 'http://schemas.xmlsoap.org/soap/envelope/';

    public function __construct(
        private readonly string $endpoint,
        private readonly AtSoapHttpTransport $transport,
        private readonly AtSecurityHeaderBuilder $securityHeader,
        private readonly AtCredentialsProvider $credentials,
        private readonly EFaturaRequestBuilder $requests,
    ) {
    }

    public function register(CompanyId $companyId, AtCommunicableDocument $document): AtCommunicationOutcome
    {
        return $this->send($companyId, $this->requests->operationFor($document), $this->requests->buildRegister($document));
    }

    public function changeStatus(CompanyId $companyId, AtCommunicableDocument $document): AtCommunicationOutcome
    {
        return $this->send($companyId, 'ChangeWorkStatus', $this->requests->buildChangeWorkStatus($document));
    }

    private function send(CompanyId $companyId, string $operation, string $body): AtCommunicationOutcome
    {
        $credentials = $this->credentials->forCompany($companyId);

        if (null === $credentials) {
            throw new AtCredentialsNotConfigured();
        }

        $digest = hash('sha256', $body);
        $envelope = \sprintf(
            '<S:Envelope xmlns:S="%s"><S:Header>%s</S:Header><S:Body>%s</S:Body></S:Envelope>',
            self::SOAP_ENVELOPE_NAMESPACE,
            $this->securityHeader->build($credentials->subuser, $credentials->password),
            $body,
        );

        try {
            $response = $this->transport->post($this->endpoint, $envelope);
        } catch (AtTransportFailed $e) {
            return AtCommunicationOutcome::transportFailure('AT could not be reached: '.$e->getMessage(), $digest);
        }

        return $this->parse($operation, $response, $digest);
    }

    private function parse(string $operation, string $response, string $digest): AtCommunicationOutcome
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($response, \LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            return AtCommunicationOutcome::transportFailure('AT answered with something that is not XML.', $digest);
        }

        $xpath = new \DOMXPath($document);

        $code = $this->firstText($xpath, "//*[local-name()='CodigoResposta']");
        $message = $this->firstText($xpath, "//*[local-name()='Mensagem']");

        if (null !== $code && 1 === preg_match('/^-?\d+$/', $code)) {
            return AtCommunicationOutcome::fromResponse($operation, (int) $code, $message ?? '', $digest);
        }

        $fault = $this->firstText($xpath, "//*[local-name()='faultstring']");

        if (null !== $fault) {
            return AtCommunicationOutcome::transportFailure('AT answered with a SOAP fault: '.$fault, $digest);
        }

        return AtCommunicationOutcome::transportFailure('AT answered with an unrecognised response.', $digest);
    }

    private function firstText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);

        $node = false === $nodes ? null : $nodes->item(0);

        return $node instanceof \DOMElement ? trim($node->textContent) : null;
    }
}
