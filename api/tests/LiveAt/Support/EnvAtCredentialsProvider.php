<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

use App\Shared\Domain\Company\AtCredentialsProvider;
use App\Shared\Domain\Company\DecryptedAtCredentials;
use App\Shared\Domain\CompanyId;

/**
 * The same credentials for every "company": the live suite has no database,
 * the sub-user comes straight from the environment.
 */
final class EnvAtCredentialsProvider implements AtCredentialsProvider
{
    public function __construct(private readonly LiveAtConfig $config)
    {
    }

    public function forCompany(CompanyId $companyId): DecryptedAtCredentials
    {
        return new DecryptedAtCredentials($this->config->subuser, $this->config->password);
    }
}
