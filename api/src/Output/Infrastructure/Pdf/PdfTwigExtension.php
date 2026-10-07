<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Pdf;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Presentation helpers for the PDF templates — formatting only, never
 * arithmetic beyond rounding a stored value for display (CLAUDE.md: the
 * calculator is the only code that computes a fiscal amount).
 *
 * pt-PT conventions: decimal comma, groups of thousands separated by a
 * no-break space (CLDR's `pt-PT`, emitted as `&nbsp;`), amounts followed by " €"; dates as
 * `AAAA-MM-DD`, one of the two formats Despacho 8632/2014 §2.2.4 allows.
 */
final class PdfTwigExtension extends AbstractExtension
{
    /** The HTML entity rather than the raw character: unambiguous in every engine and in the HTML tests read. */
    private const NO_BREAK_SPACE = '&nbsp;';

    public function __construct(private readonly QrCodeImage $qrCode)
    {
    }

    public function getFilters(): array
    {
        return [
            // Digits, a comma, `&nbsp;` and `€` only — safe to emit unescaped.
            new TwigFilter('money', $this->money(...), ['is_safe' => ['html']]),
            new TwigFilter('price', $this->price(...), ['is_safe' => ['html']]),
            new TwigFilter('quantity', $this->quantity(...), ['is_safe' => ['html']]),
            new TwigFilter('percent', $this->percent(...), ['is_safe' => ['html']]),
            new TwigFilter('iso_date', $this->isoDate(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('qr_code', $this->qrCode->dataUri(...))];
    }

    /** `1 234,56 €` — an amount, two decimals. */
    public function money(string $value): string
    {
        return $this->group(BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp)->toString()).self::NO_BREAK_SPACE.'€';
    }

    /** A unit price: at least two decimals, up to six (`12,5` → `12,50`, `0,1234` stays). */
    public function price(string $value): string
    {
        return $this->group($this->trimmed(BigDecimal::of($value)->toScale(6, RoundingMode::HalfUp), 2));
    }

    /** A quantity: no trailing zeros (`2.000000` → `2`, `0.500000` → `0,5`). */
    public function quantity(string $value): string
    {
        return $this->group($this->trimmed(BigDecimal::of($value)->toScale(6, RoundingMode::HalfUp), 0));
    }

    /** `23,00%`. */
    public function percent(string $value): string
    {
        return $this->group(BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp)->toString()).'%';
    }

    public function isoDate(\DateTimeInterface $value): string
    {
        return $value->format('Y-m-d');
    }

    private function trimmed(BigDecimal $value, int $minimumScale): string
    {
        $scale = max(0, $value->getScale());

        while ($scale > $minimumScale && str_ends_with($value->toScale(max(0, $scale))->toString(), '0')) {
            --$scale;
        }

        return $value->toScale(max(0, $scale), RoundingMode::HalfUp)->toString();
    }

    /**
     * `1234567.89` → `1 234 567,89` (no-break spaces); the sign is kept.
     */
    private function group(string $decimal): string
    {
        $negative = str_starts_with($decimal, '-');
        $decimal = ltrim($decimal, '-');
        $parts = explode('.', $decimal, 2);
        $integer = $parts[0];
        $fraction = $parts[1] ?? null;

        $grouped = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', self::NO_BREAK_SPACE, $integer);

        return ($negative ? '-' : '').$grouped.(null === $fraction ? '' : ','.$fraction);
    }
}
