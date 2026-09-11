<?php

declare(strict_types=1);

namespace App\Company\Application\Command;

use App\Company\Domain\AtCredentials;
use App\Company\Domain\AtCredentialsEncryptor;
use App\Company\Domain\AtCredentialsRepository;
use App\Company\Domain\Exception\InvalidAtSubuserFormat;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * No AT calls here (Phase 3): this only stores the (encrypted) credentials
 * for later use — docs/plans/phase-1.md task 1.4.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class UpdateAtCredentialsHandler
{
    public function __construct(
        private readonly AtCredentialsRepository $credentials,
        private readonly AtCredentialsEncryptor $encryptor,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(UpdateAtCredentials $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('company.manage', $companyId)) {
            throw new PermissionDenied();
        }

        if (!AtCredentials::isValidSubuserFormat($command->subuser)) {
            throw new InvalidAtSubuserFormat();
        }

        $encryptedPassword = $this->encryptor->encrypt($command->password);
        $existing = $this->credentials->find($companyId);

        if (null === $existing) {
            $this->credentials->save(AtCredentials::create($companyId, $command->subuser, $encryptedPassword));
        } else {
            $existing->updateCredentials($command->subuser, $encryptedPassword);
            $this->credentials->save($existing);
        }

        // Never log the password itself, encrypted or not (CLAUDE.md —
        // secrets never appear in logs).
        $this->auditLogger->log(
            'company.at_credentials_updated',
            'AtCredentials',
            $companyId->toString(),
            ['subuser' => $command->subuser],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
