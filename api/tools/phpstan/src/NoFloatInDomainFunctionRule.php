<?php

declare(strict_types=1);

namespace Tooling\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\FunctionLike>
 */
final class NoFloatInDomainFunctionRule implements Rule
{
    private readonly DomainFloatDetector $detector;

    public function __construct()
    {
        $this->detector = new DomainFloatDetector();
    }

    public function getNodeType(): string
    {
        return Node\FunctionLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->detector->isTargetDomainFile($scope->getFile())) {
            return [];
        }

        $errors = [];

        foreach ($node->getParams() as $param) {
            if ($this->detector->typeContainsFloat($param->type)) {
                $name = $param->var instanceof Node\Expr\Variable && \is_string($param->var->name)
                    ? $param->var->name
                    : '?';
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Parameter $%s must not use float in a Domain namespace (CLAUDE.md: never use float for money, quantities, prices, rates or percentages).',
                    $name,
                ))->identifier('tcErp.noFloatInDomain')->build();
            }
        }

        if ($this->detector->typeContainsFloat($node->getReturnType())) {
            $errors[] = RuleErrorBuilder::message(
                'Return type must not use float in a Domain namespace (CLAUDE.md: never use float for money, quantities, prices, rates or percentages).',
            )->identifier('tcErp.noFloatInDomain')->build();
        }

        return $errors;
    }
}
