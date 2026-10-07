<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * technical-scope.md §5.4: a worker process is not tied to one request, so
 * it must restore the {@see RequestCompanyContext} from the envelope's
 * {@see CompanyStamp} before handling and clear it afterwards, so one worker
 * iteration can never leak its company into the next.
 *
 * A message with no stamp is left alone entirely (no set, no clear): on the
 * synchronous, in-request buses that's most messages, and the request
 * listener already owns that context's lifecycle for the rest of the
 * request — clearing it here would break a request that dispatches more
 * than one company-scoped command.
 *
 * A stamped message restores whatever context was there before it, rather
 * than blindly clearing: in a worker that is "none" (so nothing leaks into
 * the next iteration, as before), but a stamped message dispatched *during*
 * a request (docs/plans/phase-3.md task 3.2: the post-commit AT wake-up
 * sent to the transport once issuance has committed) must not wipe the
 * request's own company context.
 */
final class RestoreCompanyContextMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly RequestCompanyContext $companyContext)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(CompanyStamp::class);

        if (null === $stamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        $previous = $this->companyContext->hasCompany() ? $this->companyContext->companyId() : null;
        $this->companyContext->set(CompanyId::fromString($stamp->companyId));

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            if (null === $previous) {
                $this->companyContext->clear();
            } else {
                $this->companyContext->set($previous);
            }
        }
    }
}
