<?php

declare(strict_types=1);

namespace App\AtIntegration\Application\Command;

use App\AtIntegration\Domain\Exception\NothingToRetry;
use App\AtIntegration\Domain\Outbox\AtCommunicationOutbox;
use App\Shared\Domain\AtIntegration\AtCommunicationDispatcher;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * docs/plans/phase-3.md task 3.8's "retry" action, backend half: puts a
 * document's `failed`/`rejected` communication back in the queue with a fresh
 * attempt budget (e.g. after the AT credentials were fixed). Same permission
 * as issuing — communicating a document is part of issuing it.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class RetryAtCommunicationHandler
{
    private const SUBJECT_TYPE = 'Document';

    public function __construct(
        private readonly AtCommunicationOutbox $outbox,
        private readonly AtCommunicationDispatcher $dispatcher,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RetryAtCommunication $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.issue', $companyId)) {
            throw new PermissionDenied();
        }

        $ids = $this->outbox->requestRetry($companyId, self::SUBJECT_TYPE, $command->documentId, $this->clock->now());

        if ([] === $ids) {
            throw new NothingToRetry();
        }

        $this->auditLogger->log(
            'at_communication.retry_requested',
            self::SUBJECT_TYPE,
            $command->documentId,
            ['communication_ids' => $ids],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );

        foreach ($ids as $id) {
            $this->dispatcher->dispatchAfterCommit($companyId, $id);
        }
    }
}
