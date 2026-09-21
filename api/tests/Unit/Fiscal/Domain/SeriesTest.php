<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Domain;

use App\Fiscal\Domain\Exception\InvalidSeriesStatusTransition;
use App\Fiscal\Domain\Series;
use App\Fiscal\Domain\SeriesId;
use App\Fiscal\Domain\SeriesStatus;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

/**
 * docs/plans/phase-2.md task 2.2: every lifecycle transition, including the
 * illegal ones.
 */
final class SeriesTest extends TestCase
{
    public function testCreateStartsDraftWithNoValidationCodeAndCannotIssue(): void
    {
        $series = $this->newSeries();

        self::assertSame(SeriesStatus::Draft, $series->status());
        self::assertNull($series->validationCode());
        self::assertFalse($series->canIssue());
        self::assertNull($series->lastNumber());
    }

    public function testUpdateReplacesFieldsWhileDraft(): void
    {
        $series = $this->newSeries();

        $series->update('2026B', true, 100);

        self::assertSame('2026B', $series->code());
        self::assertTrue($series->isTraining());
        self::assertSame(100, $series->firstNumber());
    }

    public function testUpdateFailsOnceActive(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T10:00:00Z'));

        $this->expectException(InvalidSeriesStatusTransition::class);

        $series->update('2026B', true, 100);
    }

    public function testActivateSetsValidationCodeStatusAndCommunicatedDate(): void
    {
        $series = $this->newSeries();
        $now = new \DateTimeImmutable('2026-01-01T10:00:00Z');

        $series->activate('VALCODE1', $now);

        self::assertSame(SeriesStatus::Active, $series->status());
        self::assertSame('VALCODE1', $series->validationCode());
        self::assertSame($now, $series->atCommunicatedAt());
        self::assertTrue($series->canIssue());
    }

    public function testActivatingAnAlreadyActiveSeriesFails(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T10:00:00Z'));

        $this->expectException(InvalidSeriesStatusTransition::class);

        $series->activate('VALCODE2', new \DateTimeImmutable('2026-01-02T10:00:00Z'));
    }

    public function testActivatingAFinishedSeriesFails(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T10:00:00Z'));
        $series->finish(new \DateTimeImmutable('2026-06-01T10:00:00Z'));

        $this->expectException(InvalidSeriesStatusTransition::class);

        $series->activate('VALCODE2', new \DateTimeImmutable('2026-06-02T10:00:00Z'));
    }

    public function testFinishSetsStatusAndFinishedDate(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T10:00:00Z'));
        $now = new \DateTimeImmutable('2026-06-01T10:00:00Z');

        $series->finish($now);

        self::assertSame(SeriesStatus::Finished, $series->status());
        self::assertSame($now, $series->atFinishedAt());
        self::assertFalse($series->canIssue());
    }

    public function testFinishingADraftSeriesFails(): void
    {
        $series = $this->newSeries();

        $this->expectException(InvalidSeriesStatusTransition::class);

        $series->finish(new \DateTimeImmutable('2026-01-01T10:00:00Z'));
    }

    public function testFinishingAnAlreadyFinishedSeriesFails(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T10:00:00Z'));
        $series->finish(new \DateTimeImmutable('2026-06-01T10:00:00Z'));

        $this->expectException(InvalidSeriesStatusTransition::class);

        $series->finish(new \DateTimeImmutable('2026-06-02T10:00:00Z'));
    }

    public function testCancelFromDraftSetsCancelledStatus(): void
    {
        $series = $this->newSeries();

        $series->cancel();

        self::assertSame(SeriesStatus::Cancelled, $series->status());
    }

    public function testCancelFromActiveSetsCancelledStatus(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T10:00:00Z'));

        $series->cancel();

        self::assertSame(SeriesStatus::Cancelled, $series->status());
        self::assertFalse($series->canIssue());
    }

    public function testCancellingAFinishedSeriesFails(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T10:00:00Z'));
        $series->finish(new \DateTimeImmutable('2026-06-01T10:00:00Z'));

        $this->expectException(InvalidSeriesStatusTransition::class);

        $series->cancel();
    }

    public function testCancellingAnAlreadyCancelledSeriesFails(): void
    {
        $series = $this->newSeries();
        $series->cancel();

        $this->expectException(InvalidSeriesStatusTransition::class);

        $series->cancel();
    }

    private function newSeries(): Series
    {
        return Series::create(SeriesId::generate(), CompanyId::generate(), 'FT', '2026A', false, 1);
    }
}
