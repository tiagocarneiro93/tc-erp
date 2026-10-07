<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

/**
 * The generated file does not validate against AT's own schema — a bug in
 * the generator or in data that should have been impossible (issued documents
 * are immutable and were validated on the way in), never something the caller
 * can fix. Deliberately a plain exception → generic 500 with the full list in
 * the logs/Sentry (docs/plans/phase-3.md decision 11's "should never happen"
 * category), and the file is never offered for download.
 */
final class SaftValidationFailed extends \RuntimeException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(\sprintf('The generated SAF-T file is not valid against SAFTPT1.04_01.xsd (%d violation(s)); first: %s', \count($errors), $errors[0] ?? 'unknown'));
    }
}
