<?php

declare(strict_types=1);

namespace App\AtIntegration\Domain\Efatura;

use App\Shared\Domain\AtIntegration\AtCommunicableDocument;
use App\Shared\Domain\AtIntegration\AtCommunicableLine;
use App\Shared\Domain\AtIntegration\AtCommunicableTaxBucket;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Builds the SOAP *body* element of the e-Fatura requests (`Fatcorews.wsdl`,
 * `at-ws-efatura-aspetos-especificos.pdf` §2.1.1.1 `RegisterInvoiceRequest`,
 * §2.1.4.1 `RegisterWorkRequest`, §2.1.5.1 `ChangeWorkStatusRequest`) from
 * stored document data. Element names, order and optionality follow the
 * WSDL's own embedded XSD, which the unit tests validate every output
 * against — so a drift from AT's contract fails here, not in production.
 *
 * Decisions, each tied to a source:
 *
 * - `SoftwareCertificateNumber` = the configured certificate number, `0`
 *   while uncertified (manual item 1.5; docs/plans/phase-3.md decision 6).
 *   `HashCharacters` follows it: "o 1º, 11º, 21º e 31º carateres do Hash do
 *   documento **ou o valor «0» (zero), caso o documento seja gerado por um
 *   programa não certificado**" (item 1.6.9), so `0` while uncertified.
 * - `ATCUD`: the document's real ATCUD. The manual still says "preenchido com
 *   «0» (zero) até à sua regulamentação", but the ATCUD has been regulated
 *   (Portaria 195/2020) and the WSDL type accepts any 1–100 character string.
 * - `TaxEntity` = `Global` (item 1.4, "caso contrário"); `AuditFileVersion` =
 *   `1.04_01` (the SAF-T version this system exports, `SAFTPT1.04_01.xsd`);
 *   `eFaturaMDVersion` = `0.0.1` (the only value the WSDL accepts).
 * - `DebitCreditIndicator`: `D` for a credit note, `C` otherwise — AT's own
 *   `saft-pt-sample-instance.xml` puts NC lines in `DebitAmount` and FT lines
 *   in `CreditAmount`.
 * - `Amount` (not `TotalTaxBase`): the two are mutually exclusive and the
 *   manual's own example uses `Amount`.
 * - `LineSummary` groups by tax bucket × exemption code × tax point date ×
 *   origin document (item 1.6.14); the amounts are reconciled to the stored
 *   `document_tax_summary` so they always add up to `NetTotal`.
 */
final class EFaturaRequestBuilder
{
    public const NAMESPACE = 'http://factemi.at.min_financas.pt/documents';

    private const WEBSERVICE_VERSION = '0.0.1';
    private const AUDIT_FILE_VERSION = '1.04_01';
    private const TAX_ENTITY = 'Global';

    public function __construct(private readonly int $softwareCertificateNumber = 0)
    {
    }

    /**
     * `RegisterInvoice` for `SalesInvoices` documents, `RegisterWork` for
     * `WorkingDocuments`.
     */
    public function operationFor(AtCommunicableDocument $document): string
    {
        return match ($document->saftSection) {
            'SalesInvoices' => 'RegisterInvoice',
            'WorkingDocuments' => 'RegisterWork',
            default => throw new \DomainException(\sprintf('Documents of section "%s" are not communicated through the e-Fatura register operations.', $document->saftSection)),
        };
    }

    public function buildRegister(AtCommunicableDocument $document): string
    {
        return 'RegisterInvoice' === $this->operationFor($document)
            ? $this->buildRegisterInvoice($document)
            : $this->buildRegisterWork($document);
    }

    public function buildRegisterInvoice(AtCommunicableDocument $document): string
    {
        $xml = $this->open('RegisterInvoiceRequest');
        $this->writeCommonHeader($xml, $document);

        $xml->startElement('doc:InvoiceData');
        $this->writeText($xml, 'doc:InvoiceNo', $document->documentNo);
        $this->writeText($xml, 'doc:ATCUD', $document->atcud);
        $this->writeText($xml, 'doc:InvoiceDate', $this->date($document->issueDate));
        $this->writeText($xml, 'doc:InvoiceType', $document->documentType);
        $this->writeText($xml, 'doc:SelfBillingIndicator', '0');
        $this->writeText($xml, 'doc:CustomerTaxID', $document->customerTaxId);
        $this->writeText($xml, 'doc:CustomerTaxIDCountry', $document->customerCountry);
        $xml->startElement('doc:DocumentStatus');
        $this->writeText($xml, 'doc:InvoiceStatus', $document->status);
        $this->writeText($xml, 'doc:InvoiceStatusDate', $this->dateTime($document->statusAt));
        $xml->endElement();
        $this->writeText($xml, 'doc:HashCharacters', $this->hashCharacters($document));
        $this->writeText($xml, 'doc:CashVATSchemeIndicator', $document->cashVatScheme ? '1' : '0');
        $this->writeText($xml, 'doc:PaperLessIndicator', '0');
        $this->writeText($xml, 'doc:SystemEntryDate', $this->dateTime($document->systemEntryAt));
        $this->writeLineSummaries($xml, $document);
        $this->writeTotals($xml, $document);
        $xml->endElement();

        return $this->close($xml);
    }

    public function buildRegisterWork(AtCommunicableDocument $document): string
    {
        $xml = $this->open('RegisterWorkRequest');
        $this->writeCommonHeader($xml, $document);

        $xml->startElement('doc:WorkData');
        $this->writeWorkHeader($xml, $document);
        $xml->startElement('doc:DocumentStatus');
        $this->writeText($xml, 'doc:WorkStatus', $document->status);
        $this->writeText($xml, 'doc:WorkStatusDate', $this->dateTime($document->statusAt));
        $xml->endElement();
        $this->writeText($xml, 'doc:HashCharacters', $this->hashCharacters($document));
        $this->writeText($xml, 'doc:SystemEntryDate', $this->dateTime($document->systemEntryAt));
        $this->writeLineSummaries($xml, $document);
        $this->writeTotals($xml, $document);
        $xml->endElement();

        return $this->close($xml);
    }

    public function buildChangeWorkStatus(AtCommunicableDocument $document): string
    {
        $xml = $this->open('ChangeWorkStatusRequest');
        $this->writeText($xml, 'doc:eFaturaMDVersion', self::WEBSERVICE_VERSION);
        $this->writeText($xml, 'doc:TaxRegistrationNumber', $this->issuerNif($document));

        $xml->startElement('doc:WorkHeader');
        $this->writeWorkHeader($xml, $document);
        $xml->endElement();

        $xml->startElement('doc:WorkStatus');
        $this->writeText($xml, 'doc:WorkStatus', $document->status);
        $this->writeText($xml, 'doc:WorkStatusDate', $this->dateTime($document->statusAt));
        $xml->endElement();

        return $this->close($xml);
    }

    private function writeCommonHeader(\XMLWriter $xml, AtCommunicableDocument $document): void
    {
        $this->writeText($xml, 'doc:eFaturaMDVersion', self::WEBSERVICE_VERSION);
        $this->writeText($xml, 'doc:AuditFileVersion', self::AUDIT_FILE_VERSION);
        $this->writeText($xml, 'doc:TaxRegistrationNumber', $this->issuerNif($document));
        $this->writeText($xml, 'doc:TaxEntity', self::TAX_ENTITY);
        $this->writeText($xml, 'doc:SoftwareCertificateNumber', (string) $this->softwareCertificateNumber);
    }

    private function writeWorkHeader(\XMLWriter $xml, AtCommunicableDocument $document): void
    {
        $this->writeText($xml, 'doc:DocumentNumber', $document->documentNo);
        $this->writeText($xml, 'doc:ATCUD', $document->atcud);
        $this->writeText($xml, 'doc:WorkDate', $this->date($document->issueDate));
        $this->writeText($xml, 'doc:WorkType', $document->documentType);
        $this->writeText($xml, 'doc:CustomerTaxID', $document->customerTaxId);
        $this->writeText($xml, 'doc:CustomerTaxIDCountry', $document->customerCountry);
    }

    private function writeLineSummaries(\XMLWriter $xml, AtCommunicableDocument $document): void
    {
        $indicator = 'NC' === $document->documentType ? 'D' : 'C';

        foreach ($this->summarise($document) as $group) {
            $xml->startElement('doc:LineSummary');

            foreach ($group['originDocumentNos'] as $originDocumentNo) {
                $xml->startElement('doc:OrderReferences');
                $this->writeText($xml, 'doc:OriginatingON', $originDocumentNo);
                $xml->endElement();
            }

            $this->writeText($xml, 'doc:TaxPointDate', $this->date($group['taxPointDate']));

            foreach ($document->referencedDocumentNos as $referencedDocumentNo) {
                $this->writeText($xml, 'doc:Reference', $referencedDocumentNo);
            }

            $this->writeText($xml, 'doc:DebitCreditIndicator', $indicator);
            $this->writeText($xml, 'doc:Amount', $group['amount']);

            $xml->startElement('doc:Tax');
            $this->writeText($xml, 'doc:TaxType', 'IVA');
            $this->writeText($xml, 'doc:TaxCountryRegion', $group['taxRegion']);
            $this->writeText($xml, 'doc:TaxCode', $group['taxCode']);
            $this->writeText($xml, 'doc:TaxPercentage', $group['taxPercentage']);
            $xml->endElement();

            if (null !== $group['exemptionReasonCode']) {
                $this->writeText($xml, 'doc:TaxExemptionCode', $group['exemptionReasonCode']);
            }

            $xml->endElement();
        }
    }

    private function writeTotals(\XMLWriter $xml, AtCommunicableDocument $document): void
    {
        $xml->startElement('doc:DocumentTotals');
        $this->writeText($xml, 'doc:TaxPayable', $this->money($document->taxTotal));
        $this->writeText($xml, 'doc:NetTotal', $this->money($document->netTotal));
        $this->writeText($xml, 'doc:GrossTotal', $this->money($document->grossTotal));
        $xml->endElement();
    }

    /**
     * @return list<array{taxRegion: string, taxCode: string, taxPercentage: string, exemptionReasonCode: ?string, taxPointDate: \DateTimeImmutable, originDocumentNos: list<string>, amount: string}>
     */
    private function summarise(AtCommunicableDocument $document): array
    {
        $summary = [];

        foreach ($document->taxSummary as $bucket) {
            foreach ($this->summariseBucket($document, $bucket) as $group) {
                $summary[] = $group;
            }
        }

        return $summary;
    }

    /**
     * One tax bucket's `taxable_base` is authoritative; its lines only decide
     * how it splits across (exemption code × tax point date × origin
     * document). Each group's raw line total is rounded half-up to cents and
     * the last group takes whatever is left, so the groups always sum to the
     * bucket exactly.
     *
     * @return list<array{taxRegion: string, taxCode: string, taxPercentage: string, exemptionReasonCode: ?string, taxPointDate: \DateTimeImmutable, originDocumentNos: list<string>, amount: string}>
     */
    private function summariseBucket(AtCommunicableDocument $document, AtCommunicableTaxBucket $bucket): array
    {
        /** @var array<string, array{line: AtCommunicableLine, raw: BigDecimal}> $groups */
        $groups = [];

        foreach ($document->lines as $line) {
            if ($line->taxRegion !== $bucket->taxRegion || $line->taxCode !== $bucket->taxCode) {
                continue;
            }

            $taxPointDate = $this->date($line->taxPointDate);
            $key = implode('|', [$line->exemptionReasonCode ?? '', $taxPointDate, implode(',', $line->originDocumentNos)]);
            $groups[$key] ??= ['line' => $line, 'raw' => BigDecimal::zero()];
            $groups[$key]['raw'] = $groups[$key]['raw']->plus($line->netAmount);
        }

        if ([] === $groups) {
            throw new \DomainException(\sprintf('Tax bucket %s/%s of document %s has no lines.', $bucket->taxRegion, $bucket->taxCode, $document->documentNo));
        }

        $remaining = BigDecimal::of($bucket->taxableBase);
        $result = [];
        $lastKey = array_key_last($groups);

        foreach ($groups as $key => $group) {
            $amount = $key === $lastKey
                ? $remaining
                : $group['raw']->toScale(2, RoundingMode::HalfUp);
            $remaining = $remaining->minus($amount);

            $result[] = [
                'taxRegion' => $bucket->taxRegion,
                'taxCode' => $bucket->taxCode,
                'taxPercentage' => $this->percentage($bucket->taxPercentage),
                'exemptionReasonCode' => $group['line']->exemptionReasonCode,
                'taxPointDate' => $group['line']->taxPointDate,
                'originDocumentNos' => $group['line']->originDocumentNos,
                'amount' => $amount->toScale(2, RoundingMode::HalfUp)->toString(),
            ];
        }

        return $result;
    }

    private function hashCharacters(AtCommunicableDocument $document): string
    {
        return 0 === $this->softwareCertificateNumber ? '0' : $document->hashCharacters;
    }

    private function issuerNif(AtCommunicableDocument $document): string
    {
        if (1 !== preg_match('/^[1-9][0-9]{8}$/', $document->issuerNif)) {
            throw new \DomainException(\sprintf('Document %s has no valid issuer NIF to communicate under.', $document->documentNo));
        }

        return $document->issuerNif;
    }

    private function money(string $value): string
    {
        return BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp)->toString();
    }

    private function percentage(string $value): string
    {
        return BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp)->toString();
    }

    /** UTC calendar date — the same convention the signed `InvoiceDate` uses (`SigningMessage`). */
    private function date(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
    }

    /** `AAAA-MM-DDTHH:MM:SS`, UTC, no offset — the same wire format as the signed `SystemEntryDate`. */
    private function dateTime(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
    }

    private function open(string $rootElement): \XMLWriter
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(false);
        $xml->startElementNs('doc', $rootElement, self::NAMESPACE);

        return $xml;
    }

    private function close(\XMLWriter $xml): string
    {
        $xml->endElement();

        return $xml->outputMemory();
    }

    private function writeText(\XMLWriter $xml, string $element, string $value): void
    {
        $xml->writeElement($element, $value);
    }
}
