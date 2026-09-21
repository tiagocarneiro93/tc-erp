<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine\Type;

use App\Fiscal\Domain\SeriesId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class SeriesIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return SeriesId::class;
    }
}
