<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Persistence\Doctrine\Type;

use App\Company\Domain\PaymentTermsId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class PaymentTermsIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return PaymentTermsId::class;
    }
}
