<?php

declare(strict_types=1);

namespace App\Output\Domain;

/**
 * technical-scope.md §6.12: `sealed_pdf|saft|attachment`. Also a CHECK
 * constraint on the column.
 */
enum StoredFileKind: string
{
    case SealedPdf = 'sealed_pdf';
    case Saft = 'saft';
    case Attachment = 'attachment';

    public function contentType(): string
    {
        return match ($this) {
            self::SealedPdf => 'application/pdf',
            self::Saft => 'application/xml',
            self::Attachment => 'application/octet-stream',
        };
    }
}
