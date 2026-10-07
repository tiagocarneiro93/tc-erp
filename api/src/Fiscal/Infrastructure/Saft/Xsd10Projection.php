<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Saft;

/**
 * AT's `SAFTPT1.04_01.xsd` is an XSD **1.1** schema (`vc:minVersion="1.1"`),
 * but PHP validates through libxml2, which implements XSD 1.0 and refuses to
 * compile it. This derives a 1.0-compatible *projection* of the official
 * file, leaving the original byte-for-byte untouched:
 *
 * - every `xs:assert` is dropped — the business rules they express are
 *   re-implemented in {@see SaftAssertionChecker}, one test each;
 * - `xs:all` with `maxOccurs="unbounded"` children (only the
 *   `GeneralLedgerEntries` debit/credit lines, which this system never
 *   writes) becomes an unbounded `xs:choice` — the closest 1.0 can say;
 * - the `vc:` versioning attributes are removed.
 *
 * Everything else — element order, types, patterns, enumerations, the
 * `xs:unique`/`xs:keyref` identity constraints — is AT's, untouched.
 */
final class Xsd10Projection
{
    private const XS = 'http://www.w3.org/2001/XMLSchema';
    private const VC = 'http://www.w3.org/2007/XMLSchema-versioning';

    private function __construct()
    {
    }

    public static function fromFile(string $path): string
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->load($path, \LIBXML_NONET)) {
                throw new \RuntimeException('The SAF-T schema could not be parsed.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        foreach (iterator_to_array($document->getElementsByTagNameNS(self::XS, 'assert')) as $assertion) {
            $assertion->parentNode?->removeChild($assertion);
        }

        foreach (iterator_to_array($document->getElementsByTagNameNS(self::XS, 'all')) as $all) {
            if (!self::hasUnboundedChild($all)) {
                continue;
            }

            $choice = $document->createElementNS(self::XS, $all->prefix ? $all->prefix.':choice' : 'choice');
            $choice->setAttribute('minOccurs', '1');
            $choice->setAttribute('maxOccurs', 'unbounded');

            while (null !== $all->firstChild) {
                $choice->appendChild($all->firstChild);
            }

            $all->parentNode?->replaceChild($choice, $all);
        }

        $root = $document->documentElement;

        if (null !== $root) {
            foreach (iterator_to_array($root->attributes ?? []) as $attribute) {
                if (self::VC === $attribute->namespaceURI) {
                    $root->removeAttributeNode($attribute);
                }
            }
        }

        return (string) $document->saveXML();
    }

    private static function hasUnboundedChild(\DOMElement $all): bool
    {
        foreach ($all->childNodes as $child) {
            if ($child instanceof \DOMElement && 'unbounded' === $child->getAttribute('maxOccurs')) {
                return true;
            }
        }

        return false;
    }
}
