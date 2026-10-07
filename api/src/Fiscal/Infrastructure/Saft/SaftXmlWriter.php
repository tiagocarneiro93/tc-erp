<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Saft;

use App\Fiscal\Domain\Saft\SaftCustomer;
use App\Fiscal\Domain\Saft\SaftDataSource;
use App\Fiscal\Domain\Saft\SaftDocument;
use App\Fiscal\Domain\Saft\SaftDocumentLine;
use App\Fiscal\Domain\Saft\SaftExportPeriod;
use App\Fiscal\Domain\Saft\SaftFileGenerator;
use App\Fiscal\Domain\Saft\SaftGenerationSummary;
use App\Fiscal\Domain\Saft\SaftProduct;
use App\Fiscal\Domain\Saft\SaftReceipt;
use App\Fiscal\Domain\Saft\SaftSectionTotals;
use App\Fiscal\Domain\Saft\SaftTaxEntry;
use App\Shared\Domain\Company\CompanyFiscalIdentity;
use App\Shared\Domain\CompanyId;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Streams a SAF-T (PT) 1.04_01 *billing* file (`TaxAccountingBasis` = `F`,
 * technical-scope.md §7.7) with `XMLWriter`, straight to disk — the document
 * tree is never in memory. Element names and order follow
 * `docs/legal/SAFTPT1.04_01.xsd`; wherever the XSD alone does not say how a
 * stored value maps onto a field, the mapping follows AT's own
 * `saft-pt-sample-instance.xml` and is noted at the place it is made:
 *
 * - Line `CreditAmount`/`DebitAmount` is the line's taxable value after every
 *   discount (`net_amount`), `UnitPrice` the price *after* discounts
 *   (`net_amount ÷ quantity`), and line `SettlementAmount` the total discount
 *   given on the line (line discounts + its share of the global discount) —
 *   sample invoices `1T 1/2`, `1T 1/4`, `1T 1/5`. Credit notes use
 *   `DebitAmount`, everything else `CreditAmount` (sample `NC`).
 * - Section `TotalDebit`/`TotalCredit` leave out cancelled (`A`) documents,
 *   which are still counted in `NumberOfEntries` ({@see SaftSectionTotals}).
 * - `HashControl` is the signing-key version (`SAFPTHashControl` pattern
 *   `[0-9]+…`, sample `1`) — the configured *current* version, because
 *   documents do not yet record the version that signed them (an open item,
 *   docs/plans/phase-3.md task 3.3).
 *
 * Not yet covered, deliberately: `MovementOfGoods` (no transport documents
 * until Phase 4), `GeneralLedger*` (accounting, out of scope), supplier
 * master data, withholding tax, foreign currency.
 */
final class SaftXmlWriter implements SaftFileGenerator
{
    public const NAMESPACE = 'urn:OECD:StandardAuditFile-Tax:PT_1.04_01';
    private const AUDIT_FILE_VERSION = '1.04_01';
    private const FLUSH_EVERY_ENTRIES = 200;

    /** `SAFPTtextType…Car` maxima from the XSD. */
    private const MAX_NAME = 100;
    private const MAX_ADDRESS = 210;
    private const MAX_CITY = 50;
    private const MAX_POSTAL_CODE = 20;
    private const MAX_PRODUCT_DESCRIPTION = 200;
    private const MAX_EXEMPTION_REASON = 60;
    private const MAX_REASON = 50;
    private const MAX_UNIT = 20;

    private const TAX_DESCRIPTIONS = [
        'NOR' => 'Taxa normal',
        'INT' => 'Taxa intermédia',
        'RED' => 'Taxa reduzida',
        'ISE' => 'Isento',
        'OUT' => 'Outros',
    ];

    public function __construct(
        private readonly SaftDataSource $dataSource,
        private readonly string $producerTaxId,
        private readonly string $productId,
        private readonly string $productVersion,
        private readonly int $softwareCertificateNumber,
        private readonly string $hashControl,
    ) {
    }

    public function generate(
        CompanyId $companyId,
        CompanyFiscalIdentity $company,
        SaftExportPeriod $period,
        \DateTimeImmutable $createdAt,
        string $targetPath,
    ): SaftGenerationSummary {
        $xml = new \XMLWriter();

        if (!$xml->openUri($targetPath)) {
            throw new \RuntimeException('Could not open the SAF-T target file for writing.');
        }

        $xml->setIndent(false);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNs(null, 'AuditFile', self::NAMESPACE);

        $this->writeHeader($xml, $company, $period, $createdAt);

        $xml->startElement('MasterFiles');
        $customers = $this->writeCustomers($xml, $companyId, $period);
        $products = $this->writeProducts($xml, $companyId, $period);
        $this->writeTaxTable($xml, $companyId, $period);
        $xml->endElement(); // MasterFiles

        $invoices = $this->dataSource->documentTotals($companyId, $period, SaftDataSource::SALES_INVOICES);
        $workDocuments = $this->dataSource->documentTotals($companyId, $period, SaftDataSource::WORKING_DOCUMENTS);
        $payments = $this->dataSource->receiptTotals($companyId, $period);

        if ($invoices->entries + $workDocuments->entries + $payments->entries > 0) {
            $xml->startElement('SourceDocuments');

            if ($invoices->entries > 0) {
                $this->writeDocumentSection($xml, 'SalesInvoices', $invoices, $this->dataSource->documents($companyId, $period, SaftDataSource::SALES_INVOICES), $company);
            }

            if ($workDocuments->entries > 0) {
                $this->writeDocumentSection($xml, 'WorkingDocuments', $workDocuments, $this->dataSource->documents($companyId, $period, SaftDataSource::WORKING_DOCUMENTS), $company);
            }

            if ($payments->entries > 0) {
                $this->writePayments($xml, $payments, $this->dataSource->receipts($companyId, $period));
            }

            $xml->endElement(); // SourceDocuments
        }

        $xml->endElement(); // AuditFile
        $xml->endDocument();
        $xml->flush();

        return new SaftGenerationSummary($invoices->entries, $workDocuments->entries, $payments->entries, $customers, $products);
    }

    private function writeHeader(\XMLWriter $xml, CompanyFiscalIdentity $company, SaftExportPeriod $period, \DateTimeImmutable $createdAt): void
    {
        $xml->startElement('Header');
        $this->element($xml, 'AuditFileVersion', self::AUDIT_FILE_VERSION);
        $this->element($xml, 'CompanyID', $company->nif);
        $this->element($xml, 'TaxRegistrationNumber', $company->nif);
        $this->element($xml, 'TaxAccountingBasis', 'F');
        $this->element($xml, 'CompanyName', SaftFormat::text($company->legalName, self::MAX_NAME));

        if (null !== $company->commercialName && '' !== trim($company->commercialName)) {
            $this->element($xml, 'BusinessName', SaftFormat::text($company->commercialName, self::MAX_NAME));
        }

        $xml->startElement('CompanyAddress');
        $this->element($xml, 'AddressDetail', SaftFormat::text($company->address, self::MAX_ADDRESS));
        $this->element($xml, 'City', SaftFormat::text($company->city, self::MAX_CITY));
        $this->element($xml, 'PostalCode', SaftFormat::text($company->postalCode, self::MAX_POSTAL_CODE));
        $this->element($xml, 'Country', $company->country);
        $xml->endElement();

        $this->element($xml, 'FiscalYear', (string) $period->fiscalYear());
        $this->element($xml, 'StartDate', SaftFormat::date($period->start));
        $this->element($xml, 'EndDate', SaftFormat::date($period->end));
        $this->element($xml, 'CurrencyCode', 'EUR');
        $this->element($xml, 'DateCreated', SaftFormat::date($createdAt));
        $this->element($xml, 'TaxEntity', 'Global');
        $this->element($xml, 'ProductCompanyTaxID', $this->producerTaxId);
        $this->element($xml, 'SoftwareCertificateNumber', (string) $this->softwareCertificateNumber);
        $this->element($xml, 'ProductID', $this->productId);
        $this->element($xml, 'ProductVersion', $this->productVersion);

        if (null !== $company->email && '' !== trim($company->email)) {
            $this->element($xml, 'Email', trim($company->email));
        }

        if (null !== $company->phone && '' !== trim($company->phone)) {
            $this->element($xml, 'Telephone', trim($company->phone));
        }

        $xml->endElement();
    }

    private function writeCustomers(\XMLWriter $xml, CompanyId $companyId, SaftExportPeriod $period): int
    {
        $count = 0;

        /** @var SaftCustomer $customer */
        foreach ($this->dataSource->customers($companyId, $period) as $customer) {
            $xml->startElement('Customer');
            $this->element($xml, 'CustomerID', SaftFormat::customerId('' === $customer->customerId ? null : $customer->customerId));
            $this->element($xml, 'AccountID', SaftFormat::UNKNOWN);
            $this->element($xml, 'CustomerTaxID', SaftFormat::text($customer->taxId, 30));
            $this->element($xml, 'CompanyName', SaftFormat::text($customer->name, self::MAX_NAME));
            $xml->startElement('BillingAddress');
            $this->element($xml, 'AddressDetail', SaftFormat::text($customer->address, self::MAX_ADDRESS));
            $this->element($xml, 'City', SaftFormat::text($customer->city, self::MAX_CITY));
            $this->element($xml, 'PostalCode', SaftFormat::text($customer->postalCode, self::MAX_POSTAL_CODE));
            $this->element($xml, 'Country', $customer->country ?? 'PT');
            $xml->endElement();
            $this->element($xml, 'SelfBillingIndicator', '0');
            $xml->endElement();
            ++$count;
        }

        return $count;
    }

    private function writeProducts(\XMLWriter $xml, CompanyId $companyId, SaftExportPeriod $period): int
    {
        $count = 0;

        /** @var SaftProduct $product */
        foreach ($this->dataSource->products($companyId, $period) as $product) {
            $xml->startElement('Product');
            $this->element($xml, 'ProductType', $product->type);
            $this->element($xml, 'ProductCode', SaftFormat::text($product->code, 60));
            $this->element($xml, 'ProductDescription', $this->productDescription($product->description));
            $this->element($xml, 'ProductNumberCode', SaftFormat::text($product->code, 60));
            $xml->endElement();
            ++$count;
        }

        return $count;
    }

    private function writeTaxTable(\XMLWriter $xml, CompanyId $companyId, SaftExportPeriod $period): void
    {
        $entries = $this->dataSource->taxEntries($companyId, $period);

        if ([] === $entries) {
            return;
        }

        $xml->startElement('TaxTable');

        /** @var SaftTaxEntry $entry */
        foreach ($entries as $entry) {
            $xml->startElement('TaxTableEntry');
            $this->element($xml, 'TaxType', 'IVA');
            $this->element($xml, 'TaxCountryRegion', $entry->region);
            $this->element($xml, 'TaxCode', $entry->code);
            $this->element($xml, 'Description', self::TAX_DESCRIPTIONS[$entry->code] ?? $entry->code);
            $this->element($xml, 'TaxPercentage', SaftFormat::percentage($entry->percentage));
            $xml->endElement();
        }

        $xml->endElement();
    }

    /**
     * @param iterable<SaftDocument> $documents
     */
    private function writeDocumentSection(\XMLWriter $xml, string $section, SaftSectionTotals $totals, iterable $documents, CompanyFiscalIdentity $company): void
    {
        $isInvoices = 'SalesInvoices' === $section;

        $xml->startElement($section);
        $this->element($xml, 'NumberOfEntries', (string) $totals->entries);
        $this->element($xml, 'TotalDebit', SaftFormat::amount($totals->totalDebit));
        $this->element($xml, 'TotalCredit', SaftFormat::amount($totals->totalCredit));

        $written = 0;

        foreach ($documents as $document) {
            $isInvoices ? $this->writeInvoice($xml, $document, $company) : $this->writeWorkDocument($xml, $document);

            if (0 === ++$written % self::FLUSH_EVERY_ENTRIES) {
                $xml->flush();
            }
        }

        $xml->endElement();
    }

    private function writeInvoice(\XMLWriter $xml, SaftDocument $document, CompanyFiscalIdentity $company): void
    {
        $xml->startElement('Invoice');
        $this->element($xml, 'InvoiceNo', $document->documentNo);
        $this->element($xml, 'ATCUD', $document->atcud);

        $xml->startElement('DocumentStatus');
        $this->element($xml, 'InvoiceStatus', $document->status);
        $this->element($xml, 'InvoiceStatusDate', SaftFormat::dateTime($document->statusAt));
        $this->reason($xml, $document);
        $this->element($xml, 'SourceID', SaftFormat::shortId($document->sourceUserId));
        $this->element($xml, 'SourceBilling', 'P');
        $xml->endElement();

        $this->element($xml, 'Hash', $document->hash);
        $this->element($xml, 'HashControl', $this->hashControl);
        $this->element($xml, 'Period', (string) SaftFormat::period($document->issueDate));
        $this->element($xml, 'InvoiceDate', SaftFormat::date($document->issueDate));
        $this->element($xml, 'InvoiceType', $document->documentType);

        $xml->startElement('SpecialRegimes');
        $this->element($xml, 'SelfBillingIndicator', '0');
        $this->element($xml, 'CashVATSchemeIndicator', ($document->cashVatScheme ?? $company->cashVat) ? '1' : '0');
        $this->element($xml, 'ThirdPartiesBillingIndicator', '0');
        $xml->endElement();

        $this->element($xml, 'SourceID', SaftFormat::shortId($document->sourceUserId));
        $this->element($xml, 'SystemEntryDate', SaftFormat::dateTime($document->systemEntryAt));
        $this->element($xml, 'CustomerID', SaftFormat::customerId($document->customerId));

        $this->writeLines($xml, $document);
        $this->writeTotals($xml, $document->taxTotal, $document->netTotal, $document->grossTotal);

        $xml->endElement();
    }

    private function writeWorkDocument(\XMLWriter $xml, SaftDocument $document): void
    {
        $xml->startElement('WorkDocument');
        $this->element($xml, 'DocumentNumber', $document->documentNo);
        $this->element($xml, 'ATCUD', $document->atcud);

        $xml->startElement('DocumentStatus');
        $this->element($xml, 'WorkStatus', $document->status);
        $this->element($xml, 'WorkStatusDate', SaftFormat::dateTime($document->statusAt));
        $this->reason($xml, $document);
        $this->element($xml, 'SourceID', SaftFormat::shortId($document->sourceUserId));
        $this->element($xml, 'SourceBilling', 'P');
        $xml->endElement();

        $this->element($xml, 'Hash', $document->hash);
        $this->element($xml, 'HashControl', $this->hashControl);
        $this->element($xml, 'Period', (string) SaftFormat::period($document->issueDate));
        $this->element($xml, 'WorkDate', SaftFormat::date($document->issueDate));
        $this->element($xml, 'WorkType', $document->documentType);
        $this->element($xml, 'SourceID', SaftFormat::shortId($document->sourceUserId));
        $this->element($xml, 'SystemEntryDate', SaftFormat::dateTime($document->systemEntryAt));
        $this->element($xml, 'CustomerID', SaftFormat::customerId($document->customerId));

        $this->writeLines($xml, $document);
        $this->writeTotals($xml, $document->taxTotal, $document->netTotal, $document->grossTotal);

        $xml->endElement();
    }

    private function reason(\XMLWriter $xml, SaftDocument $document): void
    {
        if (null !== $document->statusReason && '' !== trim($document->statusReason)) {
            $this->element($xml, 'Reason', SaftFormat::text($document->statusReason, self::MAX_REASON));
        }
    }

    private function writeLines(\XMLWriter $xml, SaftDocument $document): void
    {
        $isCreditNote = 'NC' === $document->documentType;

        foreach ($document->lines as $line) {
            $xml->startElement('Line');
            $this->element($xml, 'LineNumber', (string) $line->lineNumber);

            foreach ($line->originDocumentNos as $originDocumentNo) {
                $xml->startElement('OrderReferences');
                $this->element($xml, 'OriginatingON', SaftFormat::text($originDocumentNo, 60));
                $xml->endElement();
            }

            $this->element($xml, 'ProductCode', SaftFormat::text($line->productCode, 60));
            $this->element($xml, 'ProductDescription', $this->productDescription($line->productDescription));
            $this->element($xml, 'Quantity', SaftFormat::quantity($line->quantity));
            $this->element($xml, 'UnitOfMeasure', SaftFormat::text($line->unitCode, self::MAX_UNIT));
            $this->element($xml, 'UnitPrice', SaftFormat::amount($this->unitPriceAfterDiscounts($line)));
            $this->element($xml, 'TaxPointDate', SaftFormat::date($line->taxPointDate));

            foreach ($document->referencedDocumentNos as $index => $referencedDocumentNo) {
                $xml->startElement('References');
                $this->element($xml, 'Reference', SaftFormat::text($referencedDocumentNo, 60));
                $reason = $document->referenceReasons[$index] ?? null;

                if (null !== $reason && '' !== trim($reason)) {
                    $this->element($xml, 'Reason', SaftFormat::text($reason, self::MAX_REASON));
                }

                $xml->endElement();
            }

            $this->element($xml, 'Description', $this->productDescription($line->productDescription));
            $this->element($xml, $isCreditNote ? 'DebitAmount' : 'CreditAmount', SaftFormat::amount($line->netAmount));

            $xml->startElement('Tax');
            $this->element($xml, 'TaxType', 'IVA');
            $this->element($xml, 'TaxCountryRegion', $line->taxRegion);
            $this->element($xml, 'TaxCode', $line->taxCode);
            $this->element($xml, 'TaxPercentage', SaftFormat::percentage($line->taxPercentage));
            $xml->endElement();

            if (null !== $line->exemptionReasonCode) {
                $this->element($xml, 'TaxExemptionReason', SaftFormat::text($line->exemptionReasonText ?? $line->exemptionReasonCode, self::MAX_EXEMPTION_REASON));
                $this->element($xml, 'TaxExemptionCode', $line->exemptionReasonCode);
            }

            $discount = BigDecimal::of($line->discountAmount)->plus($line->settlementAmount);

            if ($discount->isPositive()) {
                $this->element($xml, 'SettlementAmount', SaftFormat::amount($discount->toString()));
            }

            $xml->endElement();
        }
    }

    /**
     * Quantity × UnitPrice has to land on the line's `CreditAmount`/`DebitAmount`
     * (AT's sample states the unit price "após desconto"), so the unit price
     * written is the one after every discount. A presentation of two stored
     * values, not a recalculation of the document.
     */
    private function unitPriceAfterDiscounts(SaftDocumentLine $line): string
    {
        $quantity = BigDecimal::of($line->quantity);

        if ($quantity->isZero()) {
            return $line->unitPrice;
        }

        return BigDecimal::of($line->netAmount)->dividedBy($quantity, 6, RoundingMode::HalfUp)->toString();
    }

    private function writeTotals(\XMLWriter $xml, string $taxTotal, string $netTotal, string $grossTotal): void
    {
        $xml->startElement('DocumentTotals');
        $this->element($xml, 'TaxPayable', SaftFormat::amount($taxTotal));
        $this->element($xml, 'NetTotal', SaftFormat::amount($netTotal));
        $this->element($xml, 'GrossTotal', SaftFormat::amount($grossTotal));
        $xml->endElement();
    }

    /**
     * @param iterable<SaftReceipt> $receipts
     */
    private function writePayments(\XMLWriter $xml, SaftSectionTotals $totals, iterable $receipts): void
    {
        $xml->startElement('Payments');
        $this->element($xml, 'NumberOfEntries', (string) $totals->entries);
        $this->element($xml, 'TotalDebit', SaftFormat::amount($totals->totalDebit));
        $this->element($xml, 'TotalCredit', SaftFormat::amount($totals->totalCredit));

        $written = 0;

        foreach ($receipts as $receipt) {
            $this->writePayment($xml, $receipt);

            if (0 === ++$written % self::FLUSH_EVERY_ENTRIES) {
                $xml->flush();
            }
        }

        $xml->endElement();
    }

    private function writePayment(\XMLWriter $xml, SaftReceipt $receipt): void
    {
        $xml->startElement('Payment');
        $this->element($xml, 'PaymentRefNo', $receipt->documentNo);
        $this->element($xml, 'ATCUD', $receipt->atcud);
        $this->element($xml, 'Period', (string) SaftFormat::period($receipt->issueDate));
        $this->element($xml, 'TransactionDate', SaftFormat::date($receipt->issueDate));
        $this->element($xml, 'PaymentType', 'RG');

        $xml->startElement('DocumentStatus');
        $this->element($xml, 'PaymentStatus', $receipt->status);
        $this->element($xml, 'PaymentStatusDate', SaftFormat::dateTime($receipt->statusAt));

        if (null !== $receipt->statusReason && '' !== trim($receipt->statusReason)) {
            $this->element($xml, 'Reason', SaftFormat::text($receipt->statusReason, self::MAX_REASON));
        }

        $this->element($xml, 'SourceID', SaftFormat::shortId($receipt->sourceUserId));
        $this->element($xml, 'SourcePayment', 'P');
        $xml->endElement();

        $xml->startElement('PaymentMethod');
        $this->element($xml, 'PaymentMechanism', SaftFormat::paymentMechanism($receipt->paymentMethod));
        $this->element($xml, 'PaymentAmount', SaftFormat::amount($receipt->total));
        $this->element($xml, 'PaymentDate', SaftFormat::date($receipt->issueDate));
        $xml->endElement();

        $this->element($xml, 'SourceID', SaftFormat::shortId($receipt->sourceUserId));
        $this->element($xml, 'SystemEntryDate', SaftFormat::dateTime($receipt->systemEntryAt));
        $this->element($xml, 'CustomerID', SaftFormat::customerId($receipt->customerId));

        foreach ($receipt->lines as $line) {
            $xml->startElement('Line');
            $this->element($xml, 'LineNumber', (string) $line->lineNumber);
            $xml->startElement('SourceDocumentID');
            $this->element($xml, 'OriginatingON', SaftFormat::text($line->invoiceNo, 60));
            $this->element($xml, 'InvoiceDate', SaftFormat::date($line->invoiceDate));
            $xml->endElement();

            if (BigDecimal::of($line->settlementAmount)->isPositive()) {
                $this->element($xml, 'SettlementAmount', SaftFormat::amount($line->settlementAmount));
            }

            $this->element($xml, 'CreditAmount', SaftFormat::amount($line->amount));
            $xml->endElement();
        }

        // An `RG` receipt is not under the cash-VAT regime: no VAT is due on the receipt itself.
        $this->writeTotals($xml, '0.00', $receipt->total, $receipt->total);
        $xml->endElement();
    }

    private function productDescription(string $description): string
    {
        $text = SaftFormat::text($description, self::MAX_PRODUCT_DESCRIPTION);

        return mb_strlen($text) < 2 ? str_pad($text, 2, '.') : $text;
    }

    private function element(\XMLWriter $xml, string $name, string $value): void
    {
        $xml->writeElement($name, $value);
    }
}
