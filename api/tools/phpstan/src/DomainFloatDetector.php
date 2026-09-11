<?php

declare(strict_types=1);

namespace Tooling\PhpStan;

use PhpParser\Node;

/**
 * Shared logic for the "no float in Domain" rules (CLAUDE.md — hard rules:
 * "Never use float for money, quantities, prices, rates or percentages").
 */
final class DomainFloatDetector
{
    private const TARGET_MODULES = ['Tax', 'Fiscal', 'Inventory', 'Accounts'];

    public function isTargetDomainFile(string $file): bool
    {
        $modules = implode('|', self::TARGET_MODULES);

        return 1 === preg_match('#[/\\\\](?:'.$modules.')[/\\\\]Domain[/\\\\]#', $file);
    }

    public function typeContainsFloat(?Node $type): bool
    {
        if (null === $type) {
            return false;
        }

        if ($type instanceof Node\Identifier || $type instanceof Node\Name) {
            return 'float' === strtolower($type->toString());
        }

        if ($type instanceof Node\NullableType) {
            return $this->typeContainsFloat($type->type);
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            foreach ($type->types as $part) {
                if ($this->typeContainsFloat($part)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
}
