<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

/**
 * The document scenarios to send: `scenarios.dist.php` (committed defaults),
 * overridden or extended by `scenarios.local.php` (git-ignored). A scenario in
 * the local file replaces the default of the same name key by key (so
 * `['FT-basic' => ['lines' => [...]]]` changes only the lines), a new name adds
 * a scenario (it then needs `document_type` and `lines`), and `'enabled' =>
 * false` drops one. `AT_TEST_ONLY=FT,NC-basic` narrows a run to those names or
 * document types without editing anything.
 */
final class ScenarioCatalog
{
    private const DEFAULTS = [
        'customer' => ['tax_id' => '999999990', 'country' => 'PT'],
        'status' => 'N',
        'pricing_mode' => 'net',
        'rounding_method' => 'per_line',
        'references' => [],
        'then_change_status_to' => null,
        'expect' => 'accepted',
        'tamper' => null,
        'issue_date' => null,
        'enabled' => true,
    ];

    public const LINE_DEFAULTS = ['quantity' => '1', 'unit_price' => '100.00', 'tax_region' => 'PT', 'tax_code' => 'NOR', 'exemption' => null, 'discount_percent' => null];

    /**
     * @param array<string, array<string, mixed>> $defaults
     * @param array<string, array<string, mixed>> $local
     *
     * @return list<DocumentScenario> in the order of the defaults, then local additions
     */
    public static function build(array $defaults, array $local, ?string $only): array
    {
        $merged = $defaults;

        foreach ($local as $name => $override) {
            $merged[$name] = array_replace($merged[$name] ?? [], $override);
        }

        $filter = null === $only ? null : array_values(array_filter(array_map('trim', explode(',', $only)), static fn (string $item): bool => '' !== $item));
        $scenarios = [];

        foreach ($merged as $name => $raw) {
            $raw = array_replace(self::DEFAULTS, $raw);

            if (false === $raw['enabled']) {
                continue;
            }

            $type = $raw['document_type'] ?? null;

            if (!\is_string($type) || !isset($raw['lines']) || !\is_array($raw['lines']) || [] === $raw['lines']) {
                throw new \InvalidArgumentException(\sprintf('Scenario "%s" needs a document_type and at least one line.', $name));
            }

            if (null !== $filter && !\in_array($name, $filter, true) && !\in_array($type, $filter, true)) {
                continue;
            }

            /** @var array{tax_id: string, country: string} $customer */
            $customer = array_replace(self::DEFAULTS['customer'], \is_array($raw['customer'] ?? null) ? $raw['customer'] : []);

            $lines = [];
            foreach ($raw['lines'] as $line) {
                /** @var array{quantity: string, unit_price: string, tax_region: string, tax_code: string, exemption: ?string, discount_percent: ?string} $filled */
                $filled = array_replace(self::LINE_DEFAULTS, \is_array($line) ? $line : []);
                $lines[] = $filled;
            }

            /** @var list<string> $references */
            $references = array_values(\is_array($raw['references']) ? $raw['references'] : []);

            $scenarios[] = new DocumentScenario(
                (string) $name,
                $type,
                $customer['tax_id'],
                $customer['country'],
                \is_string($raw['status']) ? $raw['status'] : 'N',
                \is_string($raw['pricing_mode']) ? $raw['pricing_mode'] : 'net',
                \is_string($raw['rounding_method']) ? $raw['rounding_method'] : 'per_line',
                $lines,
                $references,
                \is_string($raw['then_change_status_to']) ? $raw['then_change_status_to'] : null,
                \is_string($raw['expect']) ? $raw['expect'] : 'accepted',
                \is_string($raw['tamper']) ? $raw['tamper'] : null,
                \is_string($raw['issue_date']) ? $raw['issue_date'] : null,
            );
        }

        return $scenarios;
    }

    /**
     * @return list<DocumentScenario>
     */
    public static function load(string $liveAtDirectory, ?string $only): array
    {
        /** @var array<string, array<string, mixed>> $defaults */
        $defaults = require $liveAtDirectory.'/scenarios.dist.php';
        $localFile = $liveAtDirectory.'/scenarios.local.php';
        /** @var array<string, array<string, mixed>> $local */
        $local = is_file($localFile) ? require $localFile : [];

        return self::build($defaults, $local, $only);
    }
}
