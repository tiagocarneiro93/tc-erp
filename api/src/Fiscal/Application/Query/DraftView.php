<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\DocumentDraft;

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
    ) {
    }

    public static function fromEntity(DocumentDraft $draft): self
    {
        return new self(
            $draft->id()->toString(),
            $draft->documentType(),
            $draft->payload(),
            $draft->calculated(),
            $draft->seriesId(),
            $draft->customerId(),
            $draft->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
