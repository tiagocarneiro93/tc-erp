<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\SeriesId;

final class CancelSeries
{
    public function __construct(
        public readonly SeriesId $seriesId,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
