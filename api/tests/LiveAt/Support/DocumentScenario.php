<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

/**
 * One document the live suite sends, as plain data (see `scenarios.dist.php`
 * for every key). Amounts are never written here: `lines` give quantities,
 * unit prices and tax codes, and {@see ScenarioDocumentFactory} runs them
 * through the real `PriceCalculator` — the same rule the product follows.
 */
final class DocumentScenario
{
    /**
     * @param list<array{quantity: string, unit_price: string, tax_region: string, tax_code: string, exemption: ?string, discount_percent: ?string}> $lines
     * @param list<string>                                                                                                                           $references scenario names whose document numbers this one references (credit/debit notes)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $documentType,
        public readonly string $customerTaxId,
        public readonly string $customerCountry,
        public readonly string $status,
        public readonly string $pricingMode,
        public readonly string $roundingMethod,
        public readonly array $lines,
        public readonly array $references,
        public readonly ?string $thenChangeStatusTo,
        public readonly string $expect,
        public readonly ?string $tamper,
        public readonly ?string $issueDate,
    ) {
    }
}
