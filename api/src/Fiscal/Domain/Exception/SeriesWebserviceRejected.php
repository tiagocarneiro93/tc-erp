<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * docs/plans/phase-3.md task 3.1: AT's own series-communication webservice
 * rejected a register/finish/cancel attempt (a validation error, a
 * duplicate code, …). The message is AT's own
 * ({@see \App\Shared\Domain\AtIntegration\SeriesWebserviceResult::$responseMessage}),
 * not reworded — an `at_communications` row recording the same rejection
 * is already written by the time this is thrown.
 */
final class SeriesWebserviceRejected extends \DomainException implements ProblemDetails
{
    public function __construct(string $operation, string $atMessage)
    {
        parent::__construct(\sprintf('AT rejected the %s request: %s', $operation, $atMessage));
    }

    public function problemType(): string
    {
        return 'series-webservice-rejected';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
