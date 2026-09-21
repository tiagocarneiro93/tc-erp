<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\DocumentDraft;
use App\Fiscal\Domain\DocumentType;
use App\Fiscal\Domain\Signing\NotAnInvoiceMention;

final class DraftView
{
    /**
     * @param array<string, mixed>      $payload
     * @param array<string, mixed>|null $calculated
     */
    public function __construct(
        public readonly string $id,
        public readonly string $documentType,
        public readonly array $payload,
        public readonly ?array $calculated,
        public readonly ?string $seriesId,
        public readonly ?string $customerId,
        public readonly string $updatedAt,
        public readonly ?string $mention,
    ) {
    }

    /**
     * `$documentType` is nullable only because a draft's `document_type`
     * column has no foreign key (task 2.4) — in practice `CreateDraft`
     * already rejects an unknown code, so this is always resolvable.
     */
    public static function fromEntity(DocumentDraft $draft, ?DocumentType $documentType): self
    {
        return new self(
            $draft->id()->toString(),
            $draft->documentType(),
            $draft->payload(),
            $draft->calculated(),
            $draft->seriesId(),
            $draft->customerId(),
            $draft->updatedAt()->format(\DateTimeInterface::ATOM),
            $documentType?->mustDeclareItIsNotAnInvoice() ? NotAnInvoiceMention::build() : null,
        );
    }
}
