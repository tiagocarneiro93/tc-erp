<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Saft;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * SAF-T's lexical conventions, in one place: how decimals, dates and
 * over-long or missing text appear in the file. Pure formatting of stored
 * values — nothing here computes a fiscal amount.
 */
final class SaftFormat
{
    /** `SAFPTtextTypeMandatory…Car` types forbid empty text; "Desconhecido" is the schema's own placeholder word (e.g. `AccountID`, `CustomerCountry`). */
    public const UNKNOWN = 'Desconhecido';

    /** The `CustomerID` used when a document has no customer record ("Consumidor final"). */
    public const ANONYMOUS_CUSTOMER_ID = 'Consumidor final';

    private function __construct()
    {
    }

    /**
     * A monetary value: stored values go up to 6 decimals; trailing zeros are
     * dropped, but never below 2 decimals (`10.000000` → `10.00`,
     * `0.123400` → `0.1234`) — the shape AT's own sample instance uses.
     */
    public static function amount(string $value): string
    {
        return self::trimmed(BigDecimal::of($value)->toScale(6, RoundingMode::HalfUp), 2);
    }

    /**
     * A quantity: `35.269000` → `35.269`, `1.000000` → `1`.
     */
    public static function quantity(string $value): string
    {
        return self::trimmed(BigDecimal::of($value)->toScale(6, RoundingMode::HalfUp), 0);
    }

    public static function percentage(string $value): string
    {
        return BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp)->toString();
    }

    /** `AAAA-MM-DD`, UTC. */
    public static function date(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
    }

    /** `AAAA-MM-DDTHH:MM:SS`, UTC, no offset — same wire format as the signed `SystemEntryDate`. */
    public static function dateTime(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
    }

    /** Accounting `Period`: the month, 1–12. */
    public static function period(\DateTimeImmutable $value): int
    {
        return (int) $value->setTimezone(new \DateTimeZone('UTC'))->format('n');
    }

    /**
     * Text cut to the schema's maximum length, or "Desconhecido" when there
     * is nothing to say.
     */
    public static function text(?string $value, int $maxLength): string
    {
        $trimmed = trim((string) $value);

        return '' === $trimmed ? self::UNKNOWN : mb_substr($trimmed, 0, $maxLength);
    }

    /**
     * `SourceID` and `CustomerID` are limited to 30 characters; this system's
     * ids are UUIDs (36). The 32 hex digits without dashes, cut to 30 — the
     * dropped 8 bits are the tail of the random part of a UUIDv7, so two
     * different ids still do not collide in practice, and the mapping is
     * stable (the same id always yields the same value).
     */
    public static function shortId(string $uuid): string
    {
        return substr(str_replace('-', '', $uuid), 0, 30);
    }

    public static function customerId(?string $customerId): string
    {
        return null === $customerId || '' === $customerId ? self::ANONYMOUS_CUSTOMER_ID : self::shortId($customerId);
    }

    /**
     * `PaymentMechanism` (`SAFTPT…PaymentMechanism`): CC credit card, CD debit
     * card, CH cheque, MB Multibanco, NU cash, TB bank transfer, OU other.
     * Receipts keep the payment method as free text, so anything unrecognised
     * is "OU".
     */
    public static function paymentMechanism(string $paymentMethod): string
    {
        return match (strtolower(trim($paymentMethod))) {
            'cash', 'numerario', 'numerário', 'nu' => 'NU',
            'transfer', 'bank_transfer', 'transferencia', 'transferência', 'tb' => 'TB',
            'card', 'credit_card', 'cartao', 'cartão', 'cc' => 'CC',
            'debit_card', 'cd' => 'CD',
            'check', 'cheque', 'ch' => 'CH',
            'multibanco', 'mb' => 'MB',
            default => 'OU',
        };
    }

    private static function trimmed(BigDecimal $value, int $minimumScale): string
    {
        $scale = max(0, $value->getScale());

        while ($scale > $minimumScale && str_ends_with($value->toScale(max(0, $scale))->toString(), '0')) {
            --$scale;
        }

        return $value->toScale(max(0, $scale), RoundingMode::HalfUp)->toString();
    }
}
