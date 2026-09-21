<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Signing;

use App\Shared\Domain\Decimal\Money;

/**
 * `at-qrcode-spec.pdf` §3/§4: builds the QR code's message — fields A–S,
 * each `{Código}:{Descrição}` with no spaces, concatenated with `*`
 * strictly in the table's order, optional fields omitted entirely when
 * absent (rule f). This is the `documents.qr_payload`/`receipts.qr_payload`
 * column value, not the rendered QR image (out of this task's scope —
 * the image is a presentation concern for Phase 3's PDF rendering).
 *
 * Verified byte-for-byte against all 4 worked examples in the spec
 * (§5.1–§5.4) — see `QrPayloadBuilderTest`.
 */
final class QrPayloadBuilder
{
    private const REGION_PREFIXES = ['I', 'J', 'K'];

    private function __construct()
    {
    }

    public static function build(QrPayloadInput $input): string
    {
        /** @var array<string, string> $fields */
        $fields = [
            'A' => $input->issuerNif(),
            'B' => $input->customerNif(),
            'C' => $input->customerCountry(),
            'D' => $input->documentTypeCode(),
            'E' => $input->documentStatus(),
            'F' => $input->documentDate()->format('Ymd'),
            'G' => $input->documentNumber(),
            'H' => $input->atcud(),
        ];

        if ($input->taxNotIndicated()) {
            // §3(h): a movement/working document issued without a VAT
            // rate uses just I1:0, omitting I2–I8 (and any J/K group).
            $fields['I1'] = '0';
        } else {
            foreach ($input->regionalTaxAmounts() as $index => $group) {
                $prefix = self::REGION_PREFIXES[$index];
                $fields[$prefix.'1'] = $group->region();
                self::addIfPresent($fields, $prefix.'2', $group->exemptBase());
                self::addIfPresent($fields, $prefix.'3', $group->reducedBase());
                self::addIfPresent($fields, $prefix.'4', $group->reducedTax());
                self::addIfPresent($fields, $prefix.'5', $group->intermediateBase());
                self::addIfPresent($fields, $prefix.'6', $group->intermediateTax());
                self::addIfPresent($fields, $prefix.'7', $group->normalBase());
                self::addIfPresent($fields, $prefix.'8', $group->normalTax());
            }
        }

        self::addIfPresent($fields, 'L', $input->notSubjectToVat());
        self::addIfPresent($fields, 'M', $input->stampDuty());
        $fields['N'] = $input->totalTaxes()->toString();
        $fields['O'] = $input->grossTotal()->toString();
        self::addIfPresent($fields, 'P', $input->withholding());
        $fields['Q'] = $input->hashFourChars();
        $fields['R'] = $input->certificateNumber();

        if (null !== $input->otherInformation() && '' !== $input->otherInformation()) {
            $fields['S'] = $input->otherInformation();
        }

        $parts = [];
        foreach ($fields as $code => $value) {
            $parts[] = $code.':'.$value;
        }

        return implode('*', $parts);
    }

    /**
     * @param array<string, string> $fields
     */
    private static function addIfPresent(array &$fields, string $code, ?Money $value): void
    {
        if (null !== $value) {
            $fields[$code] = $value->toString();
        }
    }
}
