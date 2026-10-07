<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtIntegration\Infrastructure\Soap;

use App\AtIntegration\Domain\Efatura\EFaturaRequestBuilder;
use App\AtIntegration\Domain\Security\AtRequestCipher;
use App\AtIntegration\Domain\Security\AtWsSecurityCredentials;
use App\AtIntegration\Domain\Webservice\AtCommunicationOutcome;
use App\AtIntegration\Infrastructure\Soap\AtSecurityHeaderBuilder;
use App\AtIntegration\Infrastructure\Soap\AtSoapHttpTransport;
use App\AtIntegration\Infrastructure\Soap\AtTransportFailed;
use App\AtIntegration\Infrastructure\Soap\EFaturaWSClient;
use App\Shared\Domain\AtIntegration\AtCommunicableDocument;
use App\Shared\Domain\AtIntegration\AtCommunicableLine;
use App\Shared\Domain\AtIntegration\AtCommunicableTaxBucket;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\AtCredentialsNotConfigured;
use App\Shared\Domain\Company\AtCredentialsProvider;
use App\Shared\Domain\Company\DecryptedAtCredentials;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

/**
 * The client against a scripted {@see AtSoapHttpTransport}: what goes on the
 * wire (envelope, WS-Security header, body) and how each kind of answer is
 * read. Responses mirror the shapes in `at-ws-efatura-aspetos-especificos.pdf`.
 */
final class EFaturaWSClientTest extends TestCase
{
    private const SUCCESS = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><RegisterInvoiceResponse xmlns="http://factemi.at.min_financas.pt/documents"><Response><CodigoResposta>0</CodigoResposta><Mensagem>Operação efetuada com sucesso</Mensagem><DataOperacao>2026-03-05T10:11:12</DataOperacao></Response></RegisterInvoiceResponse></soap:Body></soap:Envelope>';

    public function testItPostsAFullEnvelopeWithTheSecurityHeaderAndTheBody(): void
    {
        $transport = new ScriptedTransport(self::SUCCESS);

        $outcome = $this->client($transport)->register(CompanyId::generate(), $this->document());

        self::assertSame('https://at.test/fatcorews/ws/', $transport->url);
        self::assertStringStartsWith('<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/"><S:Header><wss:Security', (string) $transport->envelope);
        self::assertStringContainsString('<wss:Username>599999993/37</wss:Username>', (string) $transport->envelope);
        self::assertStringContainsString('<wss:Password>cGFzc3dvcmQ=</wss:Password>', (string) $transport->envelope);
        self::assertStringContainsString('<S:Body><doc:RegisterInvoiceRequest', (string) $transport->envelope);
        self::assertStringNotContainsString('s3cret', (string) $transport->envelope, 'The plaintext password never goes on the wire.');
        self::assertTrue($outcome->isAccepted());
        self::assertSame(0, $outcome->responseCode);
        self::assertSame('Operação efetuada com sucesso', $outcome->responseMessage);
    }

    public function testTheDigestIsTheHashOfTheBodyThatWasSent(): void
    {
        $transport = new ScriptedTransport(self::SUCCESS);

        $outcome = $this->client($transport)->register(CompanyId::generate(), $this->document());

        $body = (new EFaturaRequestBuilder())->buildRegister($this->document());
        self::assertSame(hash('sha256', $body), $outcome->requestDigest);
        self::assertStringContainsString($body, (string) $transport->envelope);
    }

    public function testAValidationVerdictIsARejection(): void
    {
        $response = str_replace(['<CodigoResposta>0</CodigoResposta>', 'Operação efetuada com sucesso'], ['<CodigoResposta>-7</CodigoResposta>', 'Documento inválido por valores anómalos'], self::SUCCESS);

        $outcome = $this->client(new ScriptedTransport($response))->register(CompanyId::generate(), $this->document());

        self::assertSame(AtCommunicationOutcome::STATUS_REJECTED, $outcome->status);
        self::assertSame(-7, $outcome->responseCode);
        self::assertSame('Documento inválido por valores anómalos', $outcome->responseMessage);
    }

    public function testASoapFaultIsRetryable(): void
    {
        $fault = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><soap:Fault><faultcode>soap:Server</faultcode><faultstring>Internal error</faultstring></soap:Fault></soap:Body></soap:Envelope>';

        $outcome = $this->client(new ScriptedTransport($fault))->register(CompanyId::generate(), $this->document());

        self::assertTrue($outcome->isRetryable());
        self::assertStringContainsString('Internal error', $outcome->responseMessage);
    }

    public function testAnUnreachableAtIsRetryable(): void
    {
        $outcome = $this->client(new ScriptedTransport(new AtTransportFailed('Operation timed out')))->register(CompanyId::generate(), $this->document());

        self::assertTrue($outcome->isRetryable());
        self::assertNull($outcome->responseCode);
        self::assertStringContainsString('Operation timed out', $outcome->responseMessage);
        self::assertNotNull($outcome->requestDigest);
    }

    public function testAnAnswerThatIsNotXmlIsRetryable(): void
    {
        $outcome = $this->client(new ScriptedTransport('<html>502 Bad Gateway'))->register(CompanyId::generate(), $this->document());

        self::assertTrue($outcome->isRetryable());
    }

    public function testAnUnrecognisedXmlAnswerIsRetryable(): void
    {
        $outcome = $this->client(new ScriptedTransport('<unexpected/>'))->register(CompanyId::generate(), $this->document());

        self::assertTrue($outcome->isRetryable());
        self::assertStringContainsString('unrecognised', $outcome->responseMessage);
    }

    public function testChangingAWorkStatusUsesItsOwnOperation(): void
    {
        $transport = new ScriptedTransport(self::SUCCESS);

        $this->client($transport)->changeStatus(CompanyId::generate(), $this->document(section: 'WorkingDocuments', type: 'OR', status: 'F'));

        self::assertStringContainsString('<S:Body><doc:ChangeWorkStatusRequest', (string) $transport->envelope);
    }

    public function testACompanyWithoutAtCredentialsIsReportedAsNotConfigured(): void
    {
        $credentials = $this->createMock(AtCredentialsProvider::class);
        $credentials->method('forCompany')->willReturn(null);
        $transport = new ScriptedTransport(self::SUCCESS);
        $client = new EFaturaWSClient('https://at.test/', $transport, $this->securityHeader(), $credentials, new EFaturaRequestBuilder());

        try {
            $client->register(CompanyId::generate(), $this->document());
            self::fail('Expected AtCredentialsNotConfigured.');
        } catch (AtCredentialsNotConfigured) {
            self::assertNull($transport->envelope, 'Nothing is sent without credentials.');
        }
    }

    private function client(AtSoapHttpTransport $transport): EFaturaWSClient
    {
        $credentials = $this->createMock(AtCredentialsProvider::class);
        $credentials->method('forCompany')->willReturn(new DecryptedAtCredentials('599999993/37', 's3cret'));

        return new EFaturaWSClient('https://at.test/fatcorews/ws/', $transport, $this->securityHeader(), $credentials, new EFaturaRequestBuilder());
    }

    private function securityHeader(): AtSecurityHeaderBuilder
    {
        $cipher = new class implements AtRequestCipher {
            public function buildCredentials(string $password, \DateTimeImmutable $now): AtWsSecurityCredentials
            {
                return new AtWsSecurityCredentials(base64_encode('password'), base64_encode('nonce'), base64_encode($now->format('c')));
            }
        };
        $clock = new class implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-03-05T10:11:12+00:00');
            }
        };

        return new AtSecurityHeaderBuilder($cipher, $clock);
    }

    private function document(string $section = 'SalesInvoices', string $type = 'FT', string $status = 'N'): AtCommunicableDocument
    {
        return new AtCommunicableDocument(
            id: '0192e0f0-0000-7000-8000-000000000001',
            documentType: $type,
            saftSection: $section,
            documentNo: $type.' 2026A/1',
            atcud: 'CSDF7T5H-1',
            issuerNif: '508025090',
            customerTaxId: '999999990',
            customerCountry: 'PT',
            issueDate: new \DateTimeImmutable('2026-03-05T10:11:12+00:00'),
            systemEntryAt: new \DateTimeImmutable('2026-03-05T10:11:12+00:00'),
            status: $status,
            statusAt: new \DateTimeImmutable('2026-03-05T10:11:12+00:00'),
            hashCharacters: 'AbCd',
            cashVatScheme: false,
            netTotal: '100.00',
            taxTotal: '23.00',
            grossTotal: '123.00',
            referencedDocumentNos: [],
            lines: [new AtCommunicableLine('PT', 'NOR', '23.00', null, '100.000000', new \DateTimeImmutable('2026-03-05'), [])],
            taxSummary: [new AtCommunicableTaxBucket('PT', 'NOR', '23.00', '100.00', '23.00')],
        );
    }
}

final class ScriptedTransport implements AtSoapHttpTransport
{
    public ?string $url = null;
    public ?string $envelope = null;

    public function __construct(private readonly string|AtTransportFailed $answer)
    {
    }

    public function post(string $url, string $envelope): string
    {
        $this->url = $url;
        $this->envelope = $envelope;

        if ($this->answer instanceof AtTransportFailed) {
            throw $this->answer;
        }

        return $this->answer;
    }
}
