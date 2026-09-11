<?php

declare(strict_types=1);

namespace App\Company\Application\Command;

use App\Company\Domain\AtCredentials;
use App\Company\Domain\AtCredentialsEncryptor;
use App\Company\Domain\AtCredentialsRepository;
use App\Company\Domain\Exception\AtCredentialsNotConfigured;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Format/round-trip stub only — does not reach the AT webservice (that's
 * Phase 3, docs/plans/phase-1.md task 1.4). Decrypting the stored
 * ciphertext proves the round trip; a corrupt/unreadable ciphertext is a
 * server-side problem ({@see \App\Company\Domain\Exception\AtCredentialsDecryptionFailed},
 * a 500) rather than a "test failed" result, since it should never happen
 * from normal use of `UpdateAtCredentialsHandler`.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class TestAtCredentialsHandler
{
    public function __construct(
        private readonly AtCredentialsRepository $credentials,
        private readonly AtCredentialsEncryptor $encryptor,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(TestAtCredentials $command): TestAtCredentialsResult
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('company.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $credentials = $this->credentials->find($companyId);

        if (null === $credentials) {
            throw new AtCredentialsNotConfigured();
        }

        $this->encryptor->decrypt($credentials->passwordEncrypted());

        if (!AtCredentials::isValidSubuserFormat($credentials->subuser())) {
            $error = 'Subuser is not in the expected "<NIF>/<sequence number>" format.';
            $credentials->recordValidationFailure($error);
            $this->credentials->save($credentials);

            return new TestAtCredentialsResult(false, null, $error);
        }

        $now = $this->clock->now();
        $credentials->recordValidationSuccess($now);
        $this->credentials->save($credentials);

        return new TestAtCredentialsResult(true, $now->format(\DATE_ATOM), null);
    }
}
