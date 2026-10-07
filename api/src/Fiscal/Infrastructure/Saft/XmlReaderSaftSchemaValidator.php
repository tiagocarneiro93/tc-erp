<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Saft;

use App\Fiscal\Domain\Saft\SaftSchemaValidator;

/**
 * `XMLReader::setSchema()` validates while reading, node by node — a
 * year-long file is never loaded as a DOM (which `DOMDocument::schemaValidate`
 * would require). Identity constraints (`xs:unique` on `CustomerID`,
 * `ProductCode`…) are checked the same way.
 */
final class XmlReaderSaftSchemaValidator implements SaftSchemaValidator
{
    private const MAX_ERRORS_REPORTED = 20;

    private ?string $projectedSchemaPath = null;

    public function __construct(
        private readonly string $schemaPath,
        private readonly SaftAssertionChecker $assertions = new SaftAssertionChecker(),
    ) {
    }

    public function validate(string $path): array
    {
        $errors = $this->validateAgainstSchema($path);

        // The XSD 1.1 `xs:assert` rules libxml2 cannot see — only worth running on a structurally valid file.
        return [] === $errors ? $this->assertions->check($path) : $errors;
    }

    /**
     * The XSD libxml2 is given: AT's official file projected to XSD 1.0 (see
     * {@see Xsd10Projection}), cached on disk by the original's hash.
     */
    private function projectedSchema(): string
    {
        if (null !== $this->projectedSchemaPath) {
            return $this->projectedSchemaPath;
        }

        $hash = hash_file('sha256', $this->schemaPath);
        $target = \sprintf('%s/saft-xsd10-%s.xsd', sys_get_temp_dir(), substr((string) $hash, 0, 16));

        if (!is_file($target)) {
            $temporary = tempnam(sys_get_temp_dir(), 'saft-xsd-');
            file_put_contents((string) $temporary, Xsd10Projection::fromFile($this->schemaPath));
            rename((string) $temporary, $target);
        }

        return $this->projectedSchemaPath = $target;
    }

    /**
     * @return list<string>
     */
    private function validateAgainstSchema(string $path): array
    {
        $reader = new \XMLReader();

        if (!$reader->open($path, null, \LIBXML_NONET)) {
            return ['The file could not be opened for validation.'];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            if (!$reader->setSchema($this->projectedSchema())) {
                return ['The SAF-T schema could not be loaded.'];
            }

            while ($reader->read()) {
                // Reading is what drives validation.
            }

            $errors = [];

            foreach (libxml_get_errors() as $error) {
                $errors[] = \sprintf('line %d: %s', $error->line, trim($error->message));

                if (\count($errors) >= self::MAX_ERRORS_REPORTED) {
                    break;
                }
            }

            return $errors;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $reader->close();
        }
    }
}
