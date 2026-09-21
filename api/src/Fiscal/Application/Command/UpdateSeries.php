<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\SeriesId;

final class UpdateSeries
{
    public function __construct(
        public readonly SeriesId $seriesId,
        public readonly string $actingUserId,
        public readonly string $code,
        public readonly bool $isTraining,
        public readonly int $firstNumber,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
