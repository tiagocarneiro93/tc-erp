<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtIntegration\Domain\Webservice;

use App\AtIntegration\Domain\Webservice\AtCommunicationOutcome;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Response codes straight from `at-ws-efatura-aspetos-especificos.pdf`
 * §2.1.1.2 (RegisterInvoice), §2.1.4.2 (RegisterWork), §2.1.5.2
 * (ChangeWorkStatus).
 */
final class AtCommunicationOutcomeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function outcomes(): iterable
    {
        yield 'success' => ['RegisterInvoice', 0, AtCommunicationOutcome::STATUS_ACCEPTED];
        yield 'invoice already registered with the same values' => ['RegisterInvoice', -10, AtCommunicationOutcome::STATUS_ACCEPTED];
        yield 'work already registered with the same values' => ['RegisterWork', -22, AtCommunicationOutcome::STATUS_ACCEPTED];
        yield 'invoice already-registered code means nothing for a work document' => ['RegisterWork', -10, AtCommunicationOutcome::STATUS_REJECTED];
        yield 'registered with different values stays a rejection' => ['RegisterInvoice', -3, AtCommunicationOutcome::STATUS_REJECTED];
        yield 'invalid document' => ['RegisterInvoice', -1, AtCommunicationOutcome::STATUS_REJECTED];
        yield 'anomalous values' => ['RegisterInvoice', -7, AtCommunicationOutcome::STATUS_REJECTED];
        yield 'invalid exemption code' => ['RegisterInvoice', -8, AtCommunicationOutcome::STATUS_REJECTED];
        yield 'no permission for the issuer NIF' => ['RegisterInvoice', -16, AtCommunicationOutcome::STATUS_REJECTED];
        yield 'invalid software certificate number' => ['RegisterInvoice', -39, AtCommunicationOutcome::STATUS_REJECTED];
        yield 'internal error' => ['RegisterInvoice', -97, AtCommunicationOutcome::STATUS_FAILED];
        yield 'system error' => ['RegisterInvoice', -99, AtCommunicationOutcome::STATUS_FAILED];
        yield 'work system error' => ['RegisterWork', -99, AtCommunicationOutcome::STATUS_FAILED];
        yield 'wrong password / suspended access' => ['RegisterInvoice', 99, AtCommunicationOutcome::STATUS_FAILED];
        yield 'expired credential' => ['RegisterInvoice', 10, AtCommunicationOutcome::STATUS_FAILED];
        yield 'invalid SOAP request' => ['RegisterInvoice', 33, AtCommunicationOutcome::STATUS_FAILED];
        yield 'status change: document does not exist' => ['ChangeWorkStatus', -11, AtCommunicationOutcome::STATUS_REJECTED];
    }

    #[DataProvider('outcomes')]
    public function testItClassifiesEveryDocumentedResponseCode(string $operation, int $code, string $expectedStatus): void
    {
        $outcome = AtCommunicationOutcome::fromResponse($operation, $code, 'message');

        self::assertSame($expectedStatus, $outcome->status);
        self::assertSame($code, $outcome->responseCode);
        self::assertSame(AtCommunicationOutcome::STATUS_ACCEPTED === $expectedStatus, $outcome->isAccepted());
        self::assertSame(AtCommunicationOutcome::STATUS_FAILED === $expectedStatus, $outcome->isRetryable());
    }

    public function testATransportFailureIsRetryableAndHasNoResponseCode(): void
    {
        $outcome = AtCommunicationOutcome::transportFailure('timeout', 'digest');

        self::assertTrue($outcome->isRetryable());
        self::assertNull($outcome->responseCode);
        self::assertSame('digest', $outcome->requestDigest);
    }
}
