<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

use App\Fiscal\Domain\Exception\ChronologyViolation;
use App\Fiscal\Domain\Exception\InvalidSeriesStatusTransition;
use App\Fiscal\Domain\Exception\SeriesCannotIssue;
use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.6/§7.6, docs/decisions/0005: a series is scoped to
 * exactly one document type for as long as the company keeps using it — no
 * forced year rotation. Lifecycle: `draft` → `active` (once a validation
 * code exists) → `finished`/`cancelled`, the latter two both terminal.
 *
 * `lastNumber`/`lastHash`/`lastIssueDate`/`lastSystemEntryAt` are written
 * only by {@see recordIssuance()} (docs/plans/phase-2.md task 2.6), which
 * the issuance use case calls while holding this row's pessimistic lock
 * ({@see SeriesRepository::findForUpdate()}) so the
 * §6.9 invariants below hold under concurrent issuance, not just in
 * isolation.
 */
final class Series
{
    private function __construct(
        private readonly SeriesId $id,
        private readonly CompanyId $companyId,
        private readonly string $documentType,
        private string $code,
        private bool $isTraining,
        private ?string $validationCode,
        private SeriesStatus $status,
        private int $firstNumber,
        private ?int $lastNumber,
        private ?string $lastHash,
        private ?\DateTimeImmutable $lastIssueDate,
        private ?\DateTimeImmutable $lastSystemEntryAt,
        private ?\DateTimeImmutable $atCommunicatedAt,
        private ?\DateTimeImmutable $atFinishedAt,
    ) {
    }

    public static function create(
        SeriesId $id,
        CompanyId $companyId,
        string $documentType,
        string $code,
        bool $isTraining,
        int $firstNumber,
    ): self {
        return new self($id, $companyId, $documentType, $code, $isTraining, null, SeriesStatus::Draft, $firstNumber, null, null, null, null, null, null);
    }

    /**
     * Only while `draft`: once a series is `active` it may have been
     * communicated to the AT and could have issued documents referencing
     * its code, so `code`/`isTraining`/`firstNumber` freeze at that point.
     * `documentType` never changes after creation — a series' document
     * type is part of its identity (§6.6's `UNIQUE (company_id,
     * document_type, code)`), not an editable field.
     */
    public function update(string $code, bool $isTraining, int $firstNumber): void
    {
        $this->guardStatus('update', SeriesStatus::Draft);

        $this->code = $code;
        $this->isTraining = $isTraining;
        $this->firstNumber = $firstNumber;
    }

    /**
     * technical-scope.md §7.6: a series becomes `active` once it has a
     * validation code. In this phase the code is entered manually (the AT
     * series-communication webservice is Phase 3); `atCommunicatedAt`
     * records when this company considers the series communicated.
     */
    public function activate(string $validationCode, \DateTimeImmutable $now): void
    {
        $this->guardStatus('activate', SeriesStatus::Draft);

        $this->validationCode = $validationCode;
        $this->status = SeriesStatus::Active;
        $this->atCommunicatedAt = $now;
    }

    /**
     * The company is done with this series (docs/decisions/0005 — no
     * forced year rotation, so this is always a deliberate choice, never
     * automatic).
     */
    public function finish(\DateTimeImmutable $now): void
    {
        $this->guardStatus('finish', SeriesStatus::Active);

        $this->status = SeriesStatus::Finished;
        $this->atFinishedAt = $now;
    }

    /**
     * Discards a series that will never be used again. Allowed from
     * `draft` (never activated — nothing to discard beyond the record
     * itself) or `active` (per docs/plans/phase-2.md task 2.2's lifecycle
     * bullet, "active → finished/cancelled") — never from a terminal
     * status.
     */
    public function cancel(): void
    {
        if (SeriesStatus::Draft !== $this->status && SeriesStatus::Active !== $this->status) {
            throw new InvalidSeriesStatusTransition('cancel', $this->status);
        }

        $this->status = SeriesStatus::Cancelled;
    }

    /**
     * §7.6: "a series cannot issue documents without a validation code" —
     * true only once `active`, which under this entity's own invariants
     * already implies a validation code is set ({@see activate()} sets
     * both together), but stated as its own rule since it's what
     * docs/plans/phase-2.md task 2.6's issuance use case will actually
     * check.
     */
    public function canIssue(): bool
    {
        return SeriesStatus::Active === $this->status && null !== $this->validationCode;
    }

    /**
     * The number the next issued document on this series will get —
     * `first_number` for the series' first document, `last_number + 1`
     * otherwise (§6.9: "last_number can only increase by exactly one per
     * issuance").
     */
    public function nextNumber(): int
    {
        return null === $this->lastNumber ? $this->firstNumber : $this->lastNumber + 1;
    }

    /**
     * §7.1 steps 4–11/§6.9: called once, under this row's pessimistic
     * lock, after the document to be issued has already been signed with
     * this series' current {@see lastHash()} as its previous hash — so
     * the number/hash/dates recorded here are exactly what the just-signed
     * document used, never recomputed.
     */
    public function recordIssuance(int $number, string $hash, \DateTimeImmutable $issueDate, \DateTimeImmutable $systemEntryAt): void
    {
        if (!$this->canIssue()) {
            throw new SeriesCannotIssue($this->status);
        }

        if ($number !== $this->nextNumber()) {
            throw new \LogicException(\sprintf('Expected to record number %d, got %d — the caller must sign with nextNumber() before calling recordIssuance().', $this->nextNumber(), $number));
        }

        if (null !== $this->lastIssueDate && $issueDate < $this->lastIssueDate) {
            throw new ChronologyViolation('issue_date', $issueDate, $this->lastIssueDate);
        }

        if (null !== $this->lastSystemEntryAt && $systemEntryAt < $this->lastSystemEntryAt) {
            throw new ChronologyViolation('system_entry_at', $systemEntryAt, $this->lastSystemEntryAt);
        }

        $this->lastNumber = $number;
        $this->lastHash = $hash;
        $this->lastIssueDate = $issueDate;
        $this->lastSystemEntryAt = $systemEntryAt;
    }

    private function guardStatus(string $action, SeriesStatus $required): void
    {
        if ($required !== $this->status) {
            throw new InvalidSeriesStatusTransition($action, $this->status);
        }
    }

    public function id(): SeriesId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    /**
     * FT|FS|FR|NC|ND|RG|GT|GR|GD|OR|PF|NE ({@see DocumentType::code()}).
     */
    public function documentType(): string
    {
        return $this->documentType;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function isTraining(): bool
    {
        return $this->isTraining;
    }

    public function validationCode(): ?string
    {
        return $this->validationCode;
    }

    public function status(): SeriesStatus
    {
        return $this->status;
    }

    public function firstNumber(): int
    {
        return $this->firstNumber;
    }

    public function lastNumber(): ?int
    {
        return $this->lastNumber;
    }

    public function lastHash(): ?string
    {
        return $this->lastHash;
    }

    public function lastIssueDate(): ?\DateTimeImmutable
    {
        return $this->lastIssueDate;
    }

    public function lastSystemEntryAt(): ?\DateTimeImmutable
    {
        return $this->lastSystemEntryAt;
    }

    public function atCommunicatedAt(): ?\DateTimeImmutable
    {
        return $this->atCommunicatedAt;
    }

    public function atFinishedAt(): ?\DateTimeImmutable
    {
        return $this->atFinishedAt;
    }
}
