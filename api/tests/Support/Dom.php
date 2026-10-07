<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Typed access to XPath results for tests: only `DOMElement`s, never the
 * `DOMNameSpaceNode` half of `DOMNodeList`'s declared element type.
 */
final class Dom
{
    private function __construct()
    {
    }

    /**
     * @return list<\DOMElement>
     */
    public static function elements(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): array
    {
        $nodes = null === $context ? $xpath->query($query) : $xpath->query($query, $context);
        $elements = [];

        foreach (false === $nodes ? [] : $nodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    public static function first(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): ?\DOMElement
    {
        return self::elements($xpath, $query, $context)[0] ?? null;
    }

    public static function remove(?\DOMElement $element): void
    {
        if (null !== $element) {
            $element->parentNode?->removeChild($element);
        }
    }
}
