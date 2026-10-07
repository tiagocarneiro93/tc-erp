<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

use App\Shared\Domain\CompanyId;

/**
 * Read side of the SAF-T export (technical-scope.md §7.7): everything the
 * generator needs, as lazy iterables so a year of invoices is never held in
 * memory at once. All methods need to run inside a company transaction (RLS).
 */
interface SaftDataSource
{
    public const SALES_INVOICES = 'SalesInvoices';
    public const WORKING_DOCUMENTS = 'WorkingDocuments';

    /**
     * Customers referenced by any document or receipt of the period.
     *
     * @return iterable<SaftCustomer>
     */
    public function customers(CompanyId $companyId, SaftExportPeriod $period): iterable;

    /**
     * Products on any invoice or working-document line of the period.
     *
     * @return iterable<SaftProduct>
     */
    public function products(CompanyId $companyId, SaftExportPeriod $period): iterable;

    /**
     * @return list<SaftTaxEntry>
     */
    public function taxEntries(CompanyId $companyId, SaftExportPeriod $period): array;

    /**
     * @param self::SALES_INVOICES|self::WORKING_DOCUMENTS $section
     */
    public function documentTotals(CompanyId $companyId, SaftExportPeriod $period, string $section): SaftSectionTotals;

    /**
     * @param self::SALES_INVOICES|self::WORKING_DOCUMENTS $section
     *
     * @return iterable<SaftDocument>
     */
    public function documents(CompanyId $companyId, SaftExportPeriod $period, string $section): iterable;

    public function receiptTotals(CompanyId $companyId, SaftExportPeriod $period): SaftSectionTotals;

    /**
     * @return iterable<SaftReceipt>
     */
    public function receipts(CompanyId $companyId, SaftExportPeriod $period): iterable;
}
