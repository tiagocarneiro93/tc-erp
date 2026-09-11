<?php

declare(strict_types=1);

namespace App\Parties\Application\Query;

use App\Parties\Domain\CustomerId;

final class GetCustomer
{
    public function __construct(public readonly CustomerId $customerId)
    {
    }
}
