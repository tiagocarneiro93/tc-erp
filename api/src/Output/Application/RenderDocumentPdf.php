<?php

declare(strict_types=1);

namespace App\Output\Application;

use App\Output\Domain\DocumentPrintKind;

final class RenderDocumentPdf
{
    public function __construct(
        public readonly string $documentId,
        public readonly DocumentPrintKind $kind,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
