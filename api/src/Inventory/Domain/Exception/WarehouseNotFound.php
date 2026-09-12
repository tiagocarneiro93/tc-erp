<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class WarehouseNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such warehouse.');
    }

    public function problemType(): string
    {
        return 'warehouse-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
