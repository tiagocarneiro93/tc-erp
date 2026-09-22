<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Domain;

use App\Fiscal\Domain\Exception\ChronologyViolation;
use App\Fiscal\Domain\Exception\InvalidSeriesCode;
use App\Fiscal\Domain\Exception\InvalidSeriesStatusTransition;
use App\Fiscal\Domain\Exception\SeriesCannotIssue;
use App\Fiscal\Domain\Series;
use App\Fiscal\Domain\SeriesId;
use App\Fiscal\Domain\SeriesStatus;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testNextNumberIsFirstNumberBeforeAnyIssuance(): void
    {
        $series = Series::create(SeriesId::generate(), CompanyId::generate(), 'FT', '2026A', false, 5);

        self::assertSame(5, $series->nextNumber());
    }

    public function testRecordIssuanceAdvancesLastNumberHashAndDates(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $issueDate = new \DateTimeImmutable('2026-01-02T10:00:00Z');

        $series->recordIssuance(1, 'HASH1', $issueDate, $issueDate);

        self::assertSame(1, $series->lastNumber());
        self::assertSame('HASH1', $series->lastHash());
        self::assertSame($issueDate, $series->lastIssueDate());
        self::assertSame($issueDate, $series->lastSystemEntryAt());
        self::assertSame(2, $series->nextNumber());

        $series->recordIssuance(2, 'HASH2', $issueDate, $issueDate);

        self::assertSame(2, $series->lastNumber());
        self::assertSame('HASH2', $series->lastHash());
    }

    public function testRecordIssuanceFailsWhenTheSeriesCannotIssue(): void
    {
        $series = $this->newSeries();

        $this->expectException(SeriesCannotIssue::class);

        $series->recordIssuance(1, 'HASH1', new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    public function testRecordIssuanceFailsWithTheWrongNumber(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T00:00:00Z'));

        $this->expectException(\LogicException::class);

        $series->recordIssuance(2, 'HASH1', new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    public function testRecordIssuanceRejectsAnIssueDateBeforeTheLastOne(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $series->recordIssuance(1, 'HASH1', new \DateTimeImmutable('2026-01-02T10:00:00Z'), new \DateTimeImmutable('2026-01-02T10:00:00Z'));

        $this->expectException(ChronologyViolation::class);

        $series->recordIssuance(2, 'HASH2', new \DateTimeImmutable('2026-01-02T09:00:00Z'), new \DateTimeImmutable('2026-01-02T10:00:01Z'));
    }

    public function testRecordIssuanceRejectsASystemEntryAtBeforeTheLastOne(): void
    {
        $series = $this->newSeries();
        $series->activate('VALCODE1', new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $series->recordIssuance(1, 'HASH1', new \DateTimeImmutable('2026-01-02T10:00:00Z'), new \DateTimeImmutable('2026-01-02T10:00:00Z'));

        $this->expectException(ChronologyViolation::class);

        $series->recordIssuance(2, 'HASH2', new \DateTimeImmutable('2026-01-02T10:00:01Z'), new \DateTimeImmutable('2026-01-02T09:59:59Z'));
    }

    /**
     * docs/plans/phase-3.md task 3.1c, `at-ws-series-aspetos-especificos.pdf`
     * §1.3.2.
     */
    public function testCreateRejectsACodeTooLong(): void
    {
        $this->expectException(InvalidSeriesCode::class);

        Series::create(SeriesId::generate(), CompanyId::generate(), 'FT', str_repeat('A', 36), false, 1);
    }

    public function testCreateAcceptsACodeAtTheLengthLimit(): void
    {
        $series = Series::create(SeriesId::generate(), CompanyId::generate(), 'FT', str_repeat('A', 35), false, 1);

        self::assertSame(str_repeat('A', 35), $series->code());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodeProvider(): iterable
    {
        yield 'accented character' => ['2026Ã'];
        yield 'cedilla' => ['SÉRIEÇ'];
        yield 'space' => ['2026 A'];
        yield 'slash' => ['2026/A'];
        yield 'leading dot' => ['.2026A'];
        yield 'leading underscore' => ['_2026A'];
        yield 'leading dash' => ['-2026A'];
        yield 'trailing dot' => ['2026A.'];
        yield 'trailing dash' => ['2026A-'];
        yield 'doubled separator' => ['2026--A'];
        yield 'doubled separator, mixed' => ['2026._A'];
        yield 'starts with AT, uppercase' => ['AT2026A'];
        yield 'starts with AT, lowercase' => ['at2026A'];
        yield 'starts with AT, mixed case' => ['At2026A'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidCodeProvider')]
    public function testCreateRejectsInvalidCodes(string $code): void
    {
        $this->expectException(InvalidSeriesCode::class);

        Series::create(SeriesId::generate(), CompanyId::generate(), 'FT', $code, false, 1);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validCodeProvider(): iterable
    {
        yield 'plain year+letter' => ['2026A'];
        yield 'single character' => ['A'];
        yield 'internal separators' => ['2026.A-1_2'];
        yield 'lowercase' => ['loja-lisboa'];
        yield 'contains AT, not starting with it' => ['LOJAAT'];
    }

    #[DataProvider('validCodeProvider')]
    public function testCreateAcceptsValidCodes(string $code): void
    {
        $series = Series::create(SeriesId::generate(), CompanyId::generate(), 'FT', $code, false, 1);

        self::assertSame($code, $series->code());
    }

    public function testUpdateRejectsAnInvalidCode(): void
    {
        $series = $this->newSeries();

        $this->expectException(InvalidSeriesCode::class);

        $series->update('AT-RESERVED', false, 1);
    }

    private function newSeries(): Series
    {
        return Series::create(SeriesId::generate(), CompanyId::generate(), 'FT', '2026A', false, 1);
    }
}
