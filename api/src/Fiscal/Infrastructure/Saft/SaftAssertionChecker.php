<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Saft;

/**
 * The `xs:assert` business rules of `SAFTPT1.04_01.xsd` that libxml2 cannot
 * evaluate (see {@see Xsd10Projection}), re-implemented for the document
 * sections this system writes. Each rule cites the line of the official XSD
 * it comes from; `SaftAssertionCheckerTest` has a passing and a failing
 * document for every one.
 *
 * Applied to every `Line` of `Invoice`, `WorkDocument` and `StockMovement`,
 * and to every `Payment`:
 *
 * 1. (XSD 757/763, 428/434, 555, 650/656) a line taxed at a non-zero
 *    percentage/amount has no `TaxExemptionReason`; one taxed at zero has one.
 * 2. (XSD 769, 440, 561, 662) `TaxExemptionReason` and `TaxExemptionCode` come
 *    together or not at all.
 * 3. (XSD 446/452, 668/674) a line with a `TaxBase` has `UnitPrice` 0 and a
 *    zero `DebitAmount`/`CreditAmount`.
 * 4. (XSD 775, 806) a `PaymentType` `RC` payment carries `Tax`; `RG` payments
 *    need nothing more.
 */
final class SaftAssertionChecker
{
    private const NS = 'urn:OECD:StandardAuditFile-Tax:PT_1.04_01';
    private const MAX_ERRORS = 20;
    private const DOCUMENT_ELEMENTS = ['Invoice', 'WorkDocument', 'StockMovement', 'Payment'];

    /**
     * @return list<string>
     */
    public function check(string $path): array
    {
        $reader = new \XMLReader();

        if (!$reader->open($path, null, \LIBXML_NONET)) {
            return ['The file could not be opened for the business-rule checks.'];
        }

        $errors = [];
        $previous = libxml_use_internal_errors(true);

        try {
            $more = $reader->read();

            while ($more && \count($errors) < self::MAX_ERRORS) {
                if (\XMLReader::ELEMENT === $reader->nodeType && self::NS === $reader->namespaceURI && \in_array($reader->localName, self::DOCUMENT_ELEMENTS, true)) {
                    // Expanding into a document of our own keeps the node and the XPath on the same one.
                    $scratch = new \DOMDocument();
                    $element = $reader->expand($scratch);

                    if ($element instanceof \DOMElement) {
                        $xpath = new \DOMXPath($scratch);
                        $xpath->registerNamespace('s', self::NS);
                        array_push($errors, ...$this->checkDocument($xpath, $element, $this->identify($xpath, $element)));
                    }

                    // `next()` skips the already-checked subtree and lands on the following sibling.
                    $more = $reader->next();

                    continue;
                }

                $more = $reader->read();
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $reader->close();
        }

        return \array_slice($errors, 0, self::MAX_ERRORS);
    }

    /**
     * @return list<string>
     */
    private function checkDocument(\DOMXPath $xpath, \DOMElement $document, string $label): array
    {
        $errors = [];

        if ('Payment' === $document->localName) {
            $type = $this->text($xpath, 's:PaymentType', $document);

            $taxes = $xpath->query('s:Line/s:Tax', $document);

            if ('RC' === $type && (false === $taxes || 0 === $taxes->length)) {
                $errors[] = \sprintf('%s: a payment of type RC must carry Tax.', $label);
            }
        }

        $lines = $xpath->query('s:Line', $document);

        foreach ($lines ?: [] as $line) {
            if (!$line instanceof \DOMElement) {
                continue;
            }

            $where = \sprintf('%s line %s', $label, $this->text($xpath, 's:LineNumber', $line) ?? '?');
            $reason = null !== $this->text($xpath, 's:TaxExemptionReason', $line);
            $code = null !== $this->text($xpath, 's:TaxExemptionCode', $line);

            foreach (['s:Tax/s:TaxPercentage', 's:Tax/s:TaxAmount'] as $taxField) {
                $value = $this->text($xpath, $taxField, $line);

                if (null === $value) {
                    continue;
                }

                $isZero = 0.0 === (float) $value;

                if (!$isZero && $reason) {
                    $errors[] = \sprintf('%s: TaxExemptionReason is only allowed when the tax is zero.', $where);
                }

                if ($isZero && !$reason) {
                    $errors[] = \sprintf('%s: a zero-tax line needs a TaxExemptionReason.', $where);
                }
            }

            if ($reason !== $code) {
                $errors[] = \sprintf('%s: TaxExemptionReason and TaxExemptionCode must be given together.', $where);
            }

            if (null !== $this->text($xpath, 's:TaxBase', $line)) {
                foreach (['s:UnitPrice', 's:DebitAmount', 's:CreditAmount'] as $field) {
                    $value = $this->text($xpath, $field, $line);

                    if (null !== $value && 0.0 !== (float) $value) {
                        $errors[] = \sprintf('%s: a line with a TaxBase must have %s = 0.', $where, substr($field, 2));
                    }
                }
            }
        }

        return $errors;
    }

    private function identify(\DOMXPath $xpath, \DOMElement $document): string
    {
        foreach (['s:InvoiceNo', 's:DocumentNumber', 's:PaymentRefNo'] as $field) {
            $number = $this->text($xpath, $field, $document);

            if (null !== $number) {
                return $number;
            }
        }

        return $document->localName ?? 'document';
    }

    private function text(\DOMXPath $xpath, string $query, \DOMNode $context): ?string
    {
        $nodes = $xpath->query($query, $context);
        $node = false === $nodes ? null : $nodes->item(0);

        return $node instanceof \DOMElement ? trim($node->textContent) : null;
    }
}
