<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

/**
 * Validates a generated file against the official `SAFTPT1.04_01.xsd`,
 * streaming (the file may be hundreds of megabytes).
 */
interface SaftSchemaValidator
{
    /**
     * @return list<string> human-readable schema violations; empty when valid
     */
    public function validate(string $path): array;
}
