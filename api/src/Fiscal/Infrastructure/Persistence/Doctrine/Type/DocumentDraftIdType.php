<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine\Type;

use App\Fiscal\Domain\DocumentDraftId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class DocumentDraftIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return DocumentDraftId::class;
    }
}
