<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * AT's own `Fatcorews.wsdl` embeds the XSD every e-Fatura request must obey.
 * This pulls it out as a standalone schema so tests can validate the request
 * bodies this system builds against AT's contract itself, with no network
 * and no `ext-soap`.
 */
final class FatcorewsSchema
{
    private const XSD_NAMESPACE = 'http://www.w3.org/2001/XMLSchema';
    private const TARGET_NAMESPACE = 'http://factemi.at.min_financas.pt/documents';

    /**
     * @return list<string> libxml error messages; empty when `$bodyXml` is valid
     */
    public static function validate(string $bodyXml): array
    {
        $document = new \DOMDocument();
        $document->loadXML($bodyXml, \LIBXML_NONET);

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document->schemaValidateSource(self::schemaSource());
            $errors = array_map(static fn (\LibXMLError $error): string => trim($error->message), libxml_get_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $errors;
    }

    private static ?string $schemaSource = null;

    private static function schemaSource(): string
    {
        if (null !== self::$schemaSource) {
            return self::$schemaSource;
        }

        $wsdl = new \DOMDocument();
        $wsdl->load(__DIR__.'/../../resources/wsdl/Fatcorews.wsdl', \LIBXML_NONET);

        $schema = $wsdl->getElementsByTagNameNS(self::XSD_NAMESPACE, 'schema')->item(0);

        if (!$schema instanceof \DOMElement) {
            throw new \RuntimeException('Fatcorews.wsdl has no embedded schema.');
        }

        $standalone = new \DOMDocument();
        $imported = $standalone->importNode($schema, true);

        if (!$imported instanceof \DOMElement) {
            throw new \RuntimeException('Could not import the embedded schema.');
        }

        $standalone->appendChild($imported);
        // The WSDL declares `tns` on <wsdl:definitions>, not on the schema element itself.
        $imported->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:tns', self::TARGET_NAMESPACE);

        self::$schemaSource = (string) $standalone->saveXML();

        return self::$schemaSource;
    }
}
