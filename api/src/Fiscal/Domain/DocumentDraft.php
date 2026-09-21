<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.6: mutable, never a document. CLAUDE.md hard rule:
 * "Drafts are not documents" — no code path may print, email or otherwise
 * present a draft as if it were an issued document.
 *
 * `payload` is the raw, loosely-structured JSON the client edits — the
 * same shape `POST /calculate` (task 2.1) takes, plus `series_id`/
 * `customer_id`/`references` (this document's own concerns, not
 * `PriceCalculator`'s). `seriesId`/`customerId` are real columns per
 * §6.6's schema, kept only for querying — `payload` is their single
 * source of truth, re-derived from it every time `payload` is set, never
 * settable independently.
 *
 * `calculated` mirrors `POST /calculate`'s response for the current
 * `payload`, recomputed on every {@see updatePayload()} — see
 * `Fiscal\Application\Command\{Create,Update}DraftHandler`, which call
 * `Shared\Domain\Tax\PriceCalculationService` for this. Null whenever the
 * current payload isn't calculable yet (e.g. still missing a line) — a
 * draft is allowed to be momentarily incomplete; only issuance (task 2.6)
 * and this module's own {@see \App\Fiscal\Application\Query\ValidateDraft}
 * require a non-null `calculated`.
 */
final class DocumentDraft
{
    /**
     * @param array<string, mixed>      $payload
     * @param array<string, mixed>|null $calculated
     */
    private function __construct(
        private readonly DocumentDraftId $id,
        private readonly CompanyId $companyId,
        private readonly string $documentType,
        private array $payload,
        private ?string $seriesId,
        private ?string $customerId,
        private ?array $calculated,
        private readonly string $createdBy,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed>      $payload
     * @param array<string, mixed>|null $calculated
     */
    public static function create(
        DocumentDraftId $id,
        CompanyId $companyId,
        string $documentType,
        array $payload,
        ?array $calculated,
        string $createdBy,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            $id,
            $companyId,
            $documentType,
            $payload,
            self::extractString($payload, 'series_id'),
            self::extractString($payload, 'customer_id'),
            $calculated,
            $createdBy,
            $now,
        );
    }

    /**
     * @param array<string, mixed>      $payload
     * @param array<string, mixed>|null $calculated
     */
    public function updatePayload(array $payload, ?array $calculated, \DateTimeImmutable $now): void
    {
        $this->payload = $payload;
        $this->seriesId = self::extractString($payload, 'series_id');
        $this->customerId = self::extractString($payload, 'customer_id');
        $this->calculated = $calculated;
        $this->updatedAt = $now;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function extractString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public function id(): DocumentDraftId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    /**
     * FT|FS|FR|NC|ND|RG|GT|GR|GD|OR|PF|NE ({@see DocumentType::code()}) —
     * fixed at creation; a draft doesn't change what it's a draft of.
     */
    public function documentType(): string
    {
        return $this->documentType;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function calculated(): ?array
    {
        return $this->calculated;
    }

    public function seriesId(): ?string
    {
        return $this->seriesId;
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    public function createdBy(): string
    {
        return $this->createdBy;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
