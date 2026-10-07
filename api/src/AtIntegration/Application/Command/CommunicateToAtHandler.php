<?php

declare(strict_types=1);

namespace App\AtIntegration\Application\Command;

use App\AtIntegration\Domain\Outbox\AtCommunicationOutbox;
use App\AtIntegration\Domain\Outbox\ClaimedAtCommunication;
use App\AtIntegration\Domain\Outbox\RetryPolicy;
use App\AtIntegration\Domain\Webservice\AtCommunicationOutcome;
use App\AtIntegration\Domain\Webservice\AtDocumentWebserviceClient;
use App\Shared\Domain\AtIntegration\AtCommunicableDocument;
use App\Shared\Domain\AtIntegration\AtCommunicableDocumentReader;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\AtCredentialsNotConfigured;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\TransactionManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * technical-scope.md §7.5 — the outbox consumer. Three short steps, and the
 * network call deliberately sits *between* transactions, never inside one:
 *
 * 1. claim the row and read the document (one transaction): `pending`/`failed`
 *    → `sending`, attempt counted, lease taken. Nothing to claim (already
 *    done, or another worker holds it) means this message was a duplicate
 *    wake-up and is simply dropped.
 * 2. call AT, no transaction open.
 * 3. record what AT answered (one transaction).
 *
 * If the process dies after step 1 the row is left `sending` with a lease;
 * once it expires the sweeper (§5.4) finds it again — nothing is lost, and
 * at worst AT is asked twice, which it answers with its "already registered"
 * code (see {@see AtCommunicationOutcome}). Runs on `at.bus`, which has no
 * `doctrine_transaction` middleware precisely so step 2 isn't wrapped in one.
 *
 * Never lets an error escape: every failure becomes a recorded `failed`/
 * `rejected` outcome with its own backoff ({@see RetryPolicy}), not a second,
 * competing Messenger retry on top.
 */
#[AsMessageHandler(bus: 'at.bus')]
final class CommunicateToAtHandler
{
    public function __construct(
        private readonly AtCommunicationOutbox $outbox,
        private readonly AtCommunicableDocumentReader $documents,
        private readonly AtDocumentWebserviceClient $client,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CommunicateToAt $message): void
    {
        $companyId = CompanyId::fromString($message->companyId);

        /** @var array{0: ClaimedAtCommunication, 1: AtCommunicableDocument|null}|null $claimed */
        $claimed = $this->transactions->transactional(function () use ($companyId, $message): ?array {
            $communication = $this->outbox->claim($companyId, $message->communicationId, $this->clock->now());

            if (null === $communication) {
                return null;
            }

            $document = 'Document' === $communication->subjectType
                ? $this->documents->find($companyId, $communication->subjectId)
                : null;

            return [$communication, $document];
        });

        if (null === $claimed) {
            return;
        }

        [$communication, $document] = $claimed;

        if (null === $document) {
            $this->record($companyId, $communication, AtCommunicationOutcome::fromResponse('RegisterInvoice', -1, 'The document this communication refers to no longer exists.'));

            return;
        }

        $this->record($companyId, $communication, $this->send($companyId, $communication, $document));
    }

    private function send(CompanyId $companyId, ClaimedAtCommunication $communication, AtCommunicableDocument $document): AtCommunicationOutcome
    {
        try {
            return 'document_status' === $communication->kind
                ? $this->client->changeStatus($companyId, $document)
                : $this->client->register($companyId, $document);
        } catch (AtCredentialsNotConfigured) {
            return AtCommunicationOutcome::transportFailure('AT credentials are not configured for this company.');
        } catch (\Throwable $e) {
            // Deployment/contract problems (missing certificate, an unbuildable request…) —
            // surfaced to Sentry through the logger, retried on the normal backoff.
            $this->logger->error('AT communication failed unexpectedly.', ['communication_id' => $communication->id, 'exception' => $e]);

            return AtCommunicationOutcome::transportFailure('AT communication failed: '.$e::class);
        }
    }

    private function record(CompanyId $companyId, ClaimedAtCommunication $communication, AtCommunicationOutcome $outcome): void
    {
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($companyId, $communication, $outcome, $now): void {
            $this->outbox->recordOutcome(
                $companyId,
                $communication->id,
                $outcome->status,
                $outcome->responseCode,
                $outcome->responseMessage,
                $outcome->requestDigest,
                $outcome->isRetryable() ? RetryPolicy::nextAttemptAt($now, $communication->attempts) : null,
                $now,
            );
        });
    }
}
