<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\SeriesId;

final class GetSeries
{
    public function __construct(
        public readonly SeriesId $seriesId,
    ) {
    }
}
