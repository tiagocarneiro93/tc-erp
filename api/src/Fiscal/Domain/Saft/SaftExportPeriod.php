<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

use App\Fiscal\Domain\Exception\InvalidSaftPeriod;

/**
 * The inclusive `StartDate`..`EndDate` of one SAF-T file. Calendar dates in
 * UTC — the same convention as every other fiscal date in this system
 * (`SigningMessage`, the QR code, `documents.issue_date`).
 *
 * Limited to one calendar year because the header's `FiscalYear` is one
 * number (`SAFTPT1.04_01.xsd`: `FiscalYear` `xs:integer` 2000–9999); a period
 * crossing New Year would need two files.
 */
final class SaftExportPeriod
{
    public readonly \DateTimeImmutable $start;
    public readonly \DateTimeImmutable $end;

    public function __construct(\DateTimeImmutable $start, \DateTimeImmutable $end)
    {
        $utc = new \DateTimeZone('UTC');
        $this->start = $start->setTimezone($utc)->setTime(0, 0);
        $this->end = $end->setTimezone($utc)->setTime(0, 0);

        if ($this->end < $this->start) {
            throw InvalidSaftPeriod::endBeforeStart();
        }

        if ($this->start->format('Y') !== $this->end->format('Y')) {
            throw InvalidSaftPeriod::spansMoreThanOneYear();
        }

        if ((int) $this->start->format('Y') < 2000) {
            throw InvalidSaftPeriod::beforeTheSchemaAllows();
        }
    }

    public function fiscalYear(): int
    {
        return (int) $this->start->format('Y');
    }

    /** Inclusive lower bound for `issue_date`. */
    public function from(): \DateTimeImmutable
    {
        return $this->start;
    }

    /** Exclusive upper bound for `issue_date` — midnight after the end date. */
    public function until(): \DateTimeImmutable
    {
        return $this->end->modify('+1 day');
    }
}
