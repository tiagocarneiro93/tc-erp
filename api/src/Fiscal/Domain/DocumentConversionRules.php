<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

/**
 * technical-scope.md §6.8: which conversions {@see \App\Fiscal\Application\Command\CreateConversionDraftHandler}
 * actually supports in this phase — the full `OR → NE → GR/GT → FT` chain
 * needs stock-movement documents (Phase 5). Extracted from that handler so
 * `GetDocumentHandler` can compute the same "what can this document become"
 * answer for the detail screen without re-deriving or duplicating the rule.
 */
final class DocumentConversionRules
{
    private const ALLOWED_TARGETS = [
        'OR' => ['FT'],
        'PF' => ['FT', 'FR'],
        'NE' => ['FT'],
    ];

    /**
     * @return list<string>
     */
    public static function allowedTargets(string $sourceType): array
    {
        return self::ALLOWED_TARGETS[$sourceType] ?? [];
    }
}
