<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * technical-scope.md §6.8: working-document conversion rules this phase
 * actually builds — `OR/PF/NE → FT`, plus `PF → FR`. Every other path the
 * scope names (`OR → NE`, anything into `GR`/`GT`) depends on movement
 * documents/stock (Phase 5) and isn't buildable yet; attempting one of
 * those is reported the same way as an unsupported target, not silently
 * accepted.
 */
final class ConversionNotAllowed extends \DomainException implements ProblemDetails
{
    public static function sourceTypeNotConvertible(string $documentType): self
    {
        return new self(\sprintf('Document type "%s" is not a convertible working document (only OR/PF/NE are, §6.8).', $documentType));
    }

    public static function documentIsCancelled(): self
    {
        return new self('Cannot convert a cancelled document.');
    }

    public static function targetTypeNotAllowed(string $sourceType, string $targetType): self
    {
        return new self(\sprintf('Cannot convert a %s into a %s — only OR/NE→FT and PF→FT/FR are supported (§6.8; conversions into GR/GT/NE need stock movements, Phase 5).', $sourceType, $targetType));
    }

    public static function nothingPending(): self
    {
        return new self('Every line on this document has already been fully converted.');
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function problemType(): string
    {
        return 'conversion-not-allowed';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
