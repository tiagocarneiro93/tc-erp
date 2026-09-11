<?php

declare(strict_types=1);

namespace Tooling\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\Property>
 */
final class NoFloatInDomainPropertyRule implements Rule
{
    private readonly DomainFloatDetector $detector;

    public function __construct()
    {
        $this->detector = new DomainFloatDetector();
    }

    public function getNodeType(): string
    {
        return Node\Stmt\Property::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->detector->isTargetDomainFile($scope->getFile())) {
            return [];
        }

        if (!$this->detector->typeContainsFloat($node->type)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Properties must not use float in a Domain namespace (CLAUDE.md: never use float for money, quantities, prices, rates or percentages).',
            )->identifier('tcErp.noFloatInDomain')->build(),
        ];
    }
}
