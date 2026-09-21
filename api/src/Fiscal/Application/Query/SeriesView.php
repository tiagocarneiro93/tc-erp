<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\Series;

final class SeriesView
{
    public function __construct(
        public readonly string $id,
        public readonly string $documentType,
        public readonly string $code,
        public readonly bool $isTraining,
        public readonly ?string $validationCode,
        public readonly string $status,
        public readonly int $firstNumber,
        public readonly ?int $lastNumber,
        public readonly bool $canIssue,
        public readonly ?string $atCommunicatedAt,
        public readonly ?string $atFinishedAt,
    ) {
    }

    public static function fromEntity(Series $series): self
    {
        return new self(
            $series->id()->toString(),
            $series->documentType(),
            $series->code(),
            $series->isTraining(),
            $series->validationCode(),
            $series->status()->value,
            $series->firstNumber(),
            $series->lastNumber(),
            $series->canIssue(),
            $series->atCommunicatedAt()?->format(\DateTimeInterface::ATOM),
            $series->atFinishedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
