<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Soap;

use App\AtIntegration\Domain\Security\AtRequestCipher;
use App\Shared\Domain\AtIntegration\SeriesCancellation;
use App\Shared\Domain\AtIntegration\SeriesFinalization;
use App\Shared\Domain\AtIntegration\SeriesRegistration;
use App\Shared\Domain\AtIntegration\SeriesWebserviceClient;
use App\Shared\Domain\AtIntegration\SeriesWebserviceResult;
use App\Shared\Domain\Company\AtCredentialsProvider;
use App\Shared\Domain\CompanyId;

/**
 * `SeriesWS.wsdl` (vendored from `docs/legal/`) via PHP's native
 * `ext-soap`, WS-Security header per {@see AtRequestCipher}, mutual TLS via
 * {@see AtMutualTlsCertificate}. docs/plans/phase-3.md task 3.1f.
 *
 * **Verified against AT's real test environment** (2026-09-22, owner-run:
 * `registarSerie` → `codResultOper: 2001`, real AT-issued
 * `codValidacaoSerie` starting with `AA` as documented for the test
 * environment). This also confirms {@see AtRequestCipher}'s RSA padding
 * assumption was correct. The one real bug the live test caught — fixed
 * here, see {@see self::buildSecurityHeader()} — was a missing
 * `<wss:Security>` wrapper element, not the cipher itself.
 */
final class SeriesWSClient implements SeriesWebserviceClient
{
    /**
     * `at-ws-series-aspetos-especificos.pdf` §1.3.8 — only the document
     * types this app currently issues (or will, Phase 4's GT/GR/GD).
     */
    private const DOCUMENT_CLASS_BY_TYPE = [
        'FT' => 'SI', 'FS' => 'SI', 'FR' => 'SI', 'NC' => 'SI', 'ND' => 'SI',
        'GR' => 'MG', 'GT' => 'MG', 'GA' => 'MG', 'GC' => 'MG', 'GD' => 'MG',
        'OR' => 'WD', 'PF' => 'WD', 'NE' => 'WD',
        'RC' => 'PY', 'RG' => 'PY',
    ];

    /** decision 6: uncertified software sends 0 until certification is granted. */
    private const SOFTWARE_CERTIFICATE_NUMBER = 0;

    /** §1.3.9: the only value that describes this system. */
    private const PROCESSING_MEANS = 'PI';

    /** §1.3.10: the only defined reason code. */
    private const CANCELLATION_REASON = 'ER';

    public function __construct(
        private readonly string $wsdlPath,
        private readonly string $endpoint,
        private readonly AtMutualTlsCertificate $clientCertificate,
        private readonly AtRequestCipher $cipher,
        private readonly AtCredentialsProvider $credentials,
    ) {
    }

    public function register(CompanyId $companyId, SeriesRegistration $request): SeriesWebserviceResult
    {
        $response = $this->call($companyId, 'registarSerie', [
            'serie' => $request->code,
            'tipoSerie' => $request->isTraining ? 'F' : 'N',
            'classeDoc' => $this->documentClassFor($request->documentType),
            'tipoDoc' => $request->documentType,
            'numInicialSeq' => $request->startNumber,
            'dataInicioPrevUtiliz' => $request->expectedStartDate->format('Y-m-d'),
            'numCertSWFatur' => self::SOFTWARE_CERTIFICATE_NUMBER,
            'meioProcessamento' => self::PROCESSING_MEANS,
        ]);

        return $this->toResult($this->stdClassProperty($response, 'registarSerieResp'));
    }

    public function finish(CompanyId $companyId, SeriesFinalization $request): SeriesWebserviceResult
    {
        $response = $this->call($companyId, 'finalizarSerie', [
            'serie' => $request->code,
            'classeDoc' => $this->documentClassFor($request->documentType),
            'tipoDoc' => $request->documentType,
            'codValidacaoSerie' => $request->validationCode,
            'seqUltimoDocEmitido' => $request->lastIssuedNumber,
        ]);

        return $this->toResult($this->stdClassProperty($response, 'finalizarSerieResp'));
    }

    public function cancel(CompanyId $companyId, SeriesCancellation $request): SeriesWebserviceResult
    {
        $response = $this->call($companyId, 'anularSerie', [
            'serie' => $request->code,
            'classeDoc' => $this->documentClassFor($request->documentType),
            'tipoDoc' => $request->documentType,
            'codValidacaoSerie' => $request->validationCode,
            'motivo' => self::CANCELLATION_REASON,
            'declaracaoNaoEmissao' => true,
        ]);

        return $this->toResult($this->stdClassProperty($response, 'anularSerieResp'));
    }

    private function documentClassFor(string $documentType): string
    {
        return self::DOCUMENT_CLASS_BY_TYPE[$documentType]
            ?? throw new \RuntimeException(\sprintf('No AT document class known for document type "%s".', $documentType));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function call(CompanyId $companyId, string $operation, array $parameters): \stdClass
    {
        $credentials = $this->credentials->forCompany($companyId);

        if (null === $credentials) {
            throw new \RuntimeException(\sprintf('No AT credentials configured for company "%s".', $companyId->toString()));
        }

        $client = new \SoapClient($this->wsdlPath, [
            'location' => $this->endpoint,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'local_cert' => $this->clientCertificate->pemFilePath(),
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]),
        ]);

        $client->__setSoapHeaders([$this->buildSecurityHeader($credentials->subuser, $credentials->password)]);

        $response = $client->__soapCall($operation, [$parameters]);

        if (!$response instanceof \stdClass) {
            throw new \RuntimeException(\sprintf('Unexpected AT response shape for "%s".', $operation));
        }

        return $response;
    }

    /**
     * The whole `<wss:Security>` element is built here, not just
     * `<wss:UsernameToken>` — `SoapHeader` only auto-wraps its payload in
     * an element named by its own 2nd constructor argument when that
     * payload is a plain value; a `SoapVar` with `XSD_ANYXML` (needed here
     * to inject raw, already-serialized XML) is inserted verbatim instead,
     * so `SoapHeader`'s `'Security'` argument is silently ignored and no
     * wrapper is added. Confirmed against AT's real test environment
     * (2026-09-22, `registarSerie` → `codResultOper: 2001`): the earlier
     * `UsernameToken`-only version produced a request with no `Security`
     * element at all, which AT's WS-Security processor never recognized,
     * always failing with a generic auth error before ever reaching this
     * cipher's actual field values.
     */
    private function buildSecurityHeader(string $subuser, string $password): \SoapHeader
    {
        $credentials = $this->cipher->buildCredentials($password, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $security = \sprintf(
            '<wss:Security xmlns:wss="http://schemas.xmlsoap.org/ws/2002/12/secext">'
            .'<wss:UsernameToken>'
            .'<wss:Username>%s</wss:Username>'
            .'<wss:Password>%s</wss:Password>'
            .'<wss:Nonce>%s</wss:Nonce>'
            .'<wss:Created>%s</wss:Created>'
            .'</wss:UsernameToken>'
            .'</wss:Security>',
            htmlspecialchars($subuser, \ENT_XML1),
            htmlspecialchars($credentials->passwordBase64, \ENT_XML1),
            htmlspecialchars($credentials->nonceBase64, \ENT_XML1),
            htmlspecialchars($credentials->createdBase64, \ENT_XML1),
        );

        return new \SoapHeader(
            'http://schemas.xmlsoap.org/ws/2002/12/secext',
            'Security',
            new \SoapVar($security, \XSD_ANYXML),
            false,
        );
    }

    private function stdClassProperty(\stdClass $object, string $property): \stdClass
    {
        $value = $object->{$property} ?? null;

        if (!$value instanceof \stdClass) {
            throw new \RuntimeException(\sprintf('AT response is missing "%s".', $property));
        }

        return $value;
    }

    private function toResult(\stdClass $seriesResp): SeriesWebserviceResult
    {
        $resultInfo = $seriesResp->infoResultOper ?? null;

        if (!$resultInfo instanceof \stdClass) {
            throw new \RuntimeException('AT response is missing infoResultOper.');
        }

        $code = $resultInfo->codResultOper ?? null;
        $message = $resultInfo->msgResultOper ?? null;

        if (!is_numeric($code) || !\is_string($message)) {
            throw new \RuntimeException('AT response infoResultOper has an unexpected shape.');
        }

        $validationCode = null;
        $infoSerie = $seriesResp->infoSerie ?? null;

        if ($infoSerie instanceof \stdClass) {
            $rawValidationCode = $infoSerie->codValidacaoSerie ?? null;
            $validationCode = \is_string($rawValidationCode) ? $rawValidationCode : null;
        }

        return new SeriesWebserviceResult(
            accepted: (int) $code >= 2000 && (int) $code < 3000,
            responseCode: (int) $code,
            responseMessage: $message,
            validationCode: $validationCode,
        );
    }
}
