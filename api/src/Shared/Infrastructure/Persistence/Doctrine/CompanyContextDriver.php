<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Company\CompanyContext;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class CompanyContextDriver extends AbstractDriverMiddleware
{
    public function __construct(
        \Doctrine\DBAL\Driver $driver,
        private readonly CompanyContext $companyContext,
    ) {
        parent::__construct($driver);
    }

    public function connect(array $params): DriverConnection
    {
        return new CompanyContextConnection(parent::connect($params), $this->companyContext);
    }
}
