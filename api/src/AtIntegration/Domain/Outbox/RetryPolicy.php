<?php

declare(strict_types=1);

namespace App\AtIntegration\Domain\Outbox;

/**
 * technical-scope.md §7.5: "Retries with exponential backoff". After
 * {@see self::MAX_AUTOMATIC_ATTEMPTS} the sweeper stops picking a row up —
 * it stays `failed` for a person to retry once whatever was wrong (usually
 * AT credentials) is fixed, instead of hammering AT forever.
 */
final class RetryPolicy
{
    public const MAX_AUTOMATIC_ATTEMPTS = 10;

    private const BASE_DELAY_MINUTES = 2;
    private const MAX_DELAY_MINUTES = 360;

    /** How long a claimed (`sending`) row is left alone before the sweeper assumes the worker died. */
    public const LEASE_MINUTES = 10;

    /**
     * 2, 4, 8, 16, 32, 64, 128, 256, then 360 minutes (capped).
     */
    public static function nextAttemptAt(\DateTimeImmutable $now, int $attemptsMade): \DateTimeImmutable
    {
        $minutes = self::BASE_DELAY_MINUTES * (2 ** max(0, $attemptsMade - 1));

        return $now->modify(\sprintf('+%d minutes', min($minutes, self::MAX_DELAY_MINUTES)));
    }

    public static function leaseUntil(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify(\sprintf('+%d minutes', self::LEASE_MINUTES));
    }
}
