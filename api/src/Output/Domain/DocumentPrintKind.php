<?php

declare(strict_types=1);

namespace App\Output\Domain;

/**
 * technical-scope.md §6.12 `document_prints.kind`.
 */
enum DocumentPrintKind: string
{
    case Print = 'print';
    case Download = 'download';
    case Email = 'email';
}
