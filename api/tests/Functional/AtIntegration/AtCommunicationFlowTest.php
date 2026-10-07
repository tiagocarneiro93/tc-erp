<?php

declare(strict_types=1);

namespace App\Tests\Functional\AtIntegration;

use App\AtIntegration\Application\Command\SweepAtCommunications;
use App\AtIntegration\Domain\Webservice\AtCommunicationOutcome;
use App\AtIntegration\Infrastructure\Fake\FakeAtDocumentWebserviceClient;
use App\Tests\Support\FatcorewsSchema;
use App\Tests\Support\FiscalFlowHelpers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * docs/plans/phase-3.md task 3.2, end to end through the real HTTP stack, the
 * real outbox tables and the real Messenger handlers — only AT itself is the
 * scripted {@see FakeAtDocumentWebserviceClient}, which still builds each
 * request with the production builder (and this test validates it against
 * AT's own schema).
 */
final class AtCommunicationFlowTest extends WebTestCase
{
    use FiscalFlowHelpers;

    protected function setUp(): void
    {
        FakeAtDocumentWebserviceClient::reset();
        $this->resetAtOutbox();
    }

    public function testAnIssuedInvoiceIsCommunicatedAndAccepted(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');

        $rows = $this->atCommunications($companyId);
        self::assertCount(1, $rows);
        self::assertSame('pending', $rows[0]['status']);
        self::assertSame('invoice', $rows[0]['kind']);
        self::assertSame($documentId, $rows[0]['subject_id']);

        self::assertSame(1, $this->consumeAsyncMessages(), 'Issuing wakes the consumer once, after commit.');

        $rows = $this->atCommunications($companyId);
        self::assertCount(1, $rows);
        self::assertSame('accepted', $rows[0]['status']);
        self::assertSame(1, $rows[0]['attempts']);
        self::assertSame('0', $rows[0]['response_code']);
        self::assertIsString($rows[0]['request_digest']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $rows[0]['request_digest']);

        $calls = FakeAtDocumentWebserviceClient::calls();
        self::assertCount(1, $calls);
        self::assertSame('RegisterInvoice', $calls[0]['operation']);
        self::assertSame([], FatcorewsSchema::validate($calls[0]['body']));
        self::assertSame($rows[0]['request_digest'], hash('sha256', $calls[0]['body']));
        self::assertStringContainsString('<doc:InvoiceNo>FT 2026A/1</doc:InvoiceNo>', $calls[0]['body']);
        self::assertStringContainsString('<doc:Amount>10.00</doc:Amount>', $calls[0]['body']);
        self::assertStringContainsString('<doc:GrossTotal>12.30</doc:GrossTotal>', $calls[0]['body']);
    }

    public function testAWorkingDocumentIsRegisteredAsWork(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $this->issueOfType($client, $companyId, 'OR');

        $this->consumeAsyncMessages();

        $calls = FakeAtDocumentWebserviceClient::calls();
        self::assertCount(1, $calls);
        self::assertSame('RegisterWork', $calls[0]['operation']);
        self::assertSame([], FatcorewsSchema::validate($calls[0]['body']));
        self::assertSame('accepted', $this->atCommunications($companyId)[0]['status']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function everyCommunicatedDocumentType(): iterable
    {
        foreach (['FT', 'FS', 'FR', 'NC', 'ND', 'OR', 'PF', 'NE'] as $type) {
            yield $type => [$type];
        }
    }

    #[DataProvider('everyCommunicatedDocumentType')]
    public function testEveryDocumentTypeProducesARequestAtsSchemaAccepts(string $type): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $this->issueOfType($client, $companyId, $type);

        // NC/ND are issued after a prerequisite invoice (its wake-up died with the earlier request's kernel),
        // so go through the sweeper, which finds every due row regardless.
        $this->sweep();
        $this->consumeAsyncMessages();

        $calls = FakeAtDocumentWebserviceClient::calls();
        self::assertNotEmpty($calls, $type);
        foreach ($calls as $call) {
            self::assertSame([], FatcorewsSchema::validate($call['body']), $type);
        }
        self::assertContains($type, array_map(static fn (array $call): string => explode(' ', $call['documentNo'])[0], $calls), $type);
        self::assertSame(array_fill(0, \count($calls), 'accepted'), array_column($this->atCommunications($companyId), 'status'), $type);
    }

    public function testAnAtRejectionIsStoredAndNotRetriedAutomatically(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $this->issueOfType($client, $companyId, 'FT');

        FakeAtDocumentWebserviceClient::answerNextWith(AtCommunicationOutcome::fromResponse('RegisterInvoice', -7, 'Documento inválido por valores anómalos'));
        $this->consumeAsyncMessages();

        $rows = $this->atCommunications($companyId);
        self::assertSame('rejected', $rows[0]['status']);
        self::assertSame('-7', $rows[0]['response_code']);
        self::assertSame('Documento inválido por valores anómalos', $rows[0]['response_message']);

        $this->sweep();
        self::assertSame(0, $this->consumeAsyncMessages(), 'A rejected communication is never retried by the sweeper.');
        self::assertCount(1, FakeAtDocumentWebserviceClient::calls());
    }

    public function testATransientFailureIsRetriedByTheSweeperOnceItsBackoffElapsed(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $this->issueOfType($client, $companyId, 'FT');

        FakeAtDocumentWebserviceClient::answerNextWith(AtCommunicationOutcome::transportFailure('AT could not be reached: timeout'));
        $this->consumeAsyncMessages();

        $row = $this->atCommunications($companyId)[0];
        self::assertSame('failed', $row['status']);
        self::assertSame(1, $row['attempts']);
        self::assertNull($row['response_code']);

        $this->sweep();
        self::assertSame(0, $this->consumeAsyncMessages(), 'Backoff has not elapsed yet.');

        $this->connection($companyId)->executeStatement("UPDATE at_communications SET next_attempt_at = now() - interval '1 second' WHERE company_id = ? AND kind = 'invoice'", [$companyId]);

        $this->sweep();
        self::assertSame(1, $this->consumeAsyncMessages());

        $row = $this->atCommunications($companyId)[0];
        self::assertSame('accepted', $row['status']);
        self::assertSame(2, $row['attempts']);
    }

    public function testAWorkerThatDiedMidFlightIsRecoveredByTheSweeperExactlyOnce(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $this->issueOfType($client, $companyId, 'FT');

        // Simulate: the worker claimed the row (sending, lease taken) and was killed before recording anything.
        $this->consumeAsyncMessages(); // drop the post-commit wake-up; nothing else queued
        FakeAtDocumentWebserviceClient::reset();
        $connection = $this->connection($companyId);
        $connection->executeStatement("UPDATE at_communications SET status = 'sending', attempts = 1, response_code = NULL, response_message = NULL, next_attempt_at = now() + interval '5 minutes' WHERE company_id = ? AND kind = 'invoice'", [$companyId]);

        $this->sweep();
        self::assertSame(0, $this->consumeAsyncMessages(), 'The lease has not expired: the worker may still be alive.');

        $connection->executeStatement("UPDATE at_communications SET next_attempt_at = now() - interval '1 second' WHERE company_id = ? AND kind = 'invoice'", [$companyId]);
        $this->sweep();
        $this->sweep(); // an overlapping sweep must not double-send
        self::assertSame(2, $this->consumeAsyncMessages());

        self::assertCount(1, FakeAtDocumentWebserviceClient::calls(), 'Duplicate wake-ups claim atomically: AT is asked once.');
        $row = $this->atCommunications($companyId)[0];
        self::assertSame('accepted', $row['status']);
        self::assertSame(2, $row['attempts']);
    }

    public function testTheSweeperStopsAfterTheMaximumNumberOfAutomaticAttempts(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $this->issueOfType($client, $companyId, 'FT');
        $this->consumeAsyncMessages();

        $this->connection($companyId)->executeStatement("UPDATE at_communications SET status = 'failed', attempts = 10, next_attempt_at = now() - interval '1 hour' WHERE company_id = ? AND kind = 'invoice'", [$companyId]);
        FakeAtDocumentWebserviceClient::reset();

        $this->sweep();

        self::assertSame(0, $this->consumeAsyncMessages());
        self::assertSame([], FakeAtDocumentWebserviceClient::calls());
    }

    public function testAFailedCommunicationCanBeRetriedManually(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');

        FakeAtDocumentWebserviceClient::answerNextWith(AtCommunicationOutcome::fromResponse('RegisterInvoice', -16, 'Utilizador não tem permissões para registar documentos com o NIF de emitente indicado'));
        $this->consumeAsyncMessages();
        self::assertSame('rejected', $this->atCommunications($companyId)[0]['status']);

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/{$documentId}", server: self::HEADERS);
        /** @var array{at_communication: array{status: string, response_code: string, can_retry: bool}} $view */
        $view = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('rejected', $view['at_communication']['status']);
        self::assertSame('-16', $view['at_communication']['response_code']);
        self::assertTrue($view['at_communication']['can_retry']);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/at-communication/retry", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'retry-1'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertSame('pending', $this->atCommunications($companyId)[0]['status']);
        self::assertSame(0, $this->atCommunications($companyId)[0]['attempts']);

        self::assertSame(1, $this->consumeAsyncMessages());
        self::assertSame('accepted', $this->atCommunications($companyId)[0]['status']);

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/{$documentId}", server: self::HEADERS);
        /** @var array{at_communication: array{status: string, can_retry: bool}} $view */
        $view = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('accepted', $view['at_communication']['status']);
        self::assertFalse($view['at_communication']['can_retry']);
    }

    public function testThereIsNothingToRetryOnAnAcceptedDocument(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');
        $this->consumeAsyncMessages();

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/at-communication/retry", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'retry-2'], content: '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRetryNeedsAnIdempotencyKey(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/at-communication/retry", server: self::HEADERS, content: '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testAnUncommunicatedCancelledDocumentIsRegisteredAsCancelled(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');

        // The wake-up is still queued (nothing consumed it yet) when the user cancels —
        // AT must hear about the document with its current status, A.
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/cancel", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // The cancel request rebooted the kernel; the wake-up is still pending in the outbox, so the sweeper finds it.
        $this->sweep();
        self::assertSame(1, $this->consumeAsyncMessages());

        $calls = FakeAtDocumentWebserviceClient::calls();
        self::assertCount(1, $calls);
        self::assertSame([], FatcorewsSchema::validate($calls[0]['body']));
        self::assertStringContainsString('<doc:InvoiceStatus>A</doc:InvoiceStatus>', $calls[0]['body']);
    }

    public function testAWorkingDocumentFullyConvertedAfterAcceptanceIsReportedAsFaturado(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $neSeriesId = $this->createActiveSeries($client, $companyId, 'NE', '2026A');
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');
        $neId = $this->issueDocument($client, $companyId, $neSeriesId, 'NE');
        $this->consumeAsyncMessages();
        self::assertSame('accepted', $this->atCommunications($companyId)[0]['status']);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$neId}/convert", server: self::HEADERS, content: json_encode(['document_type' => 'FT'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $draft */
        $draft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$draft['id']}", server: self::HEADERS);
        /** @var array{payload: array<string, mixed>} $draftView */
        $draftView = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $payload = $draftView['payload'];
        $payload['series_id'] = $ftSeriesId;
        $client->request('PUT', "/api/v1/companies/{$companyId}/drafts/{$draft['id']}", server: self::HEADERS, content: json_encode(['payload' => $payload], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draft['id']}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'convert-1'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $kinds = array_column($this->atCommunications($companyId), 'kind');
        self::assertSame(['invoice', 'invoice', 'document_status'], $kinds);

        FakeAtDocumentWebserviceClient::reset();
        self::assertSame(2, $this->consumeAsyncMessages(), 'The new invoice and the source document\'s status change are both woken after commit.');

        $operations = array_column(FakeAtDocumentWebserviceClient::calls(), 'operation');
        sort($operations);
        self::assertSame(['ChangeWorkStatus', 'RegisterInvoice'], $operations);

        foreach (FakeAtDocumentWebserviceClient::calls() as $call) {
            self::assertSame([], FatcorewsSchema::validate($call['body']));
            if ('ChangeWorkStatus' === $call['operation']) {
                self::assertStringContainsString('<doc:WorkStatus>F</doc:WorkStatus>', $call['body']);
            }
        }

        self::assertSame(['accepted', 'accepted', 'accepted'], array_column($this->atCommunications($companyId), 'status'));
    }

    public function testAWorkingDocumentConvertedBeforeItsRegistrationWasSentCarriesTheNewStatusInTheRegistration(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $neSeriesId = $this->createActiveSeries($client, $companyId, 'NE', '2026A');
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');
        $neId = $this->issueDocument($client, $companyId, $neSeriesId, 'NE');

        // Not consumed yet: the NE's registration is still `pending`.
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$neId}/convert", server: self::HEADERS, content: json_encode(['document_type' => 'FT'], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $draft */
        $draft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$draft['id']}", server: self::HEADERS);
        /** @var array{payload: array<string, mixed>} $draftView */
        $draftView = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $payload = $draftView['payload'];
        $payload['series_id'] = $ftSeriesId;
        $client->request('PUT', "/api/v1/companies/{$companyId}/drafts/{$draft['id']}", server: self::HEADERS, content: json_encode(['payload' => $payload], \JSON_THROW_ON_ERROR));
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draft['id']}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'convert-2'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::assertSame(['invoice', 'invoice'], array_column($this->atCommunications($companyId), 'kind'), 'No status-change row while the registration has not been accepted.');

        $this->sweep();
        $this->consumeAsyncMessages();

        $work = array_values(array_filter(FakeAtDocumentWebserviceClient::calls(), static fn (array $call): bool => 'RegisterWork' === $call['operation']));
        self::assertCount(1, $work);
        self::assertStringContainsString('<doc:WorkStatus>F</doc:WorkStatus>', $work[0]['body']);
    }

    public function testAReceiptIsNeverEnqueuedBecauseAtsWebserviceOnlyKnowsCashVatReceipts(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $ftId = $this->issueOfType($client, $companyId, 'FT');
        $rgSeriesId = $this->createActiveSeries($client, $companyId, 'RG', '2026A');
        $this->consumeAsyncMessages();

        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'receipt-1'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'cash',
            'allocations' => [['document_id' => $ftId, 'amount' => '5.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $subjects = array_column($this->atCommunications($companyId), 'subject_type');
        self::assertSame(['Document'], $subjects, 'Only the invoice is in the outbox — Fatcorews.wsdl\'s PaymentType enumerates RC only.');
    }

    public function testADocumentInATrainingSeriesIsNeverCommunicated(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueDocument($client, $companyId, $this->createActiveSeries($client, $companyId, 'FT', '2026F', training: true), 'FT');

        self::assertSame([], $this->atCommunications($companyId));
        self::assertSame(0, $this->consumeAsyncMessages());

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/{$documentId}", server: self::HEADERS);
        /** @var array{at_communication: mixed} $view */
        $view = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertNull($view['at_communication']);
    }

    public function testMissingAtCredentialsLeaveTheCommunicationFailedWithAReadableReason(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $this->issueOfType($client, $companyId, 'FT');

        // The test environment's fake client never asks for credentials, so script the very outcome
        // the consumer maps AtCredentialsNotConfigured to — covered at the unit level for the mapping itself.
        FakeAtDocumentWebserviceClient::answerNextWith(AtCommunicationOutcome::transportFailure('AT credentials are not configured for this company.'));
        $this->consumeAsyncMessages();

        $row = $this->atCommunications($companyId)[0];
        self::assertSame('failed', $row['status']);
        self::assertSame('AT credentials are not configured for this company.', $row['response_message']);
    }

    private function sweep(): void
    {
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('messenger.routable_message_bus');
        $bus->dispatch(new \Symfony\Component\Messenger\Envelope(new SweepAtCommunications(), [new \Symfony\Component\Messenger\Stamp\BusNameStamp('at.bus')]));
    }
}
