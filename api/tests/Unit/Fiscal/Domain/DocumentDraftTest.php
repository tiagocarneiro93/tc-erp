<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Domain;

use App\Fiscal\Domain\DocumentDraft;
use App\Fiscal\Domain\DocumentDraftId;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

final class DocumentDraftTest extends TestCase
{
    public function testCreateDerivesSeriesAndCustomerIdFromThePayload(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00Z');
        $draft = DocumentDraft::create(
            DocumentDraftId::generate(),
            CompanyId::generate(),
            'FT',
            ['series_id' => 'series-1', 'customer_id' => 'customer-1', 'lines' => []],
            null,
            'user-1',
            $now,
        );

        self::assertSame('FT', $draft->documentType());
        self::assertSame('series-1', $draft->seriesId());
        self::assertSame('customer-1', $draft->customerId());
        self::assertNull($draft->calculated());
        self::assertSame('user-1', $draft->createdBy());
        self::assertSame($now, $draft->updatedAt());
    }

    public function testCreateWithNoSeriesOrCustomerLeavesThemNull(): void
    {
        $draft = DocumentDraft::create(
            DocumentDraftId::generate(),
            CompanyId::generate(),
            'FT',
            ['lines' => []],
            null,
            'user-1',
            new \DateTimeImmutable(),
        );

        self::assertNull($draft->seriesId());
        self::assertNull($draft->customerId());
    }

    public function testUpdatePayloadReplacesPayloadCalculatedAndReDerivesSeriesAndCustomer(): void
    {
        $draft = DocumentDraft::create(
            DocumentDraftId::generate(),
            CompanyId::generate(),
            'FT',
            ['series_id' => 'series-1', 'customer_id' => 'customer-1'],
            null,
            'user-1',
            new \DateTimeImmutable('2026-01-01T10:00:00Z'),
        );

        $now = new \DateTimeImmutable('2026-01-02T10:00:00Z');
        $calculated = ['net_total' => '100.00'];
        $draft->updatePayload(['series_id' => 'series-2', 'lines' => [['quantity' => '1']]], $calculated, $now);

        self::assertSame(['series_id' => 'series-2', 'lines' => [['quantity' => '1']]], $draft->payload());
        self::assertSame($calculated, $draft->calculated());
        self::assertSame('series-2', $draft->seriesId());
        self::assertNull($draft->customerId(), 'customer_id is no longer in the payload, so it must clear.');
        self::assertSame($now, $draft->updatedAt());
    }

    public function testDocumentTypeIsImmutable(): void
    {
        $draft = DocumentDraft::create(
            DocumentDraftId::generate(),
            CompanyId::generate(),
            'FT',
            [],
            null,
            'user-1',
            new \DateTimeImmutable(),
        );

        $draft->updatePayload(['document_type' => 'NC'], null, new \DateTimeImmutable());

        self::assertSame('FT', $draft->documentType(), 'document_type has no setter and payload cannot override it.');
    }
}
