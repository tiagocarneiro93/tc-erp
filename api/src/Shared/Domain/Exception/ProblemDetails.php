<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * A domain exception implements this to say how it should be reported to
 * API clients (RFC 9457, technical-scope.md §9.1: stable `type` codes),
 * without Domain depending on Symfony or HTTP concepts — `httpStatus()`
 * returns a plain int, `problemType()` a plain string slug.
 */
interface ProblemDetails
{
    /**
     * A short, stable, kebab-case slug — never a message that might change
     * wording later. Rendered as `https://tc-erp.example/problems/<slug>`.
     */
    public function problemType(): string;

    public function httpStatus(): int;
}
