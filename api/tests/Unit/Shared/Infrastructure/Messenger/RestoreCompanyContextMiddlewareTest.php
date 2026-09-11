<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Messenger;

use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use App\Shared\Infrastructure\Messenger\CompanyStamp;
use App\Shared\Infrastructure\Messenger\RestoreCompanyContextMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class RestoreCompanyContextMiddlewareTest extends TestCase
{
    public function testRestoresAndClearsTheCompanyFromTheStamp(): void
    {
        $context = new RequestCompanyContext();
        $middleware = new RestoreCompanyContextMiddleware($context);
        $companyId = CompanyId::generate();

        $seenDuringHandling = null;
        $stack = $this->stackThatCalls(static function () use ($context, &$seenDuringHandling): void {
            $seenDuringHandling = $context->hasCompany() ? $context->companyId() : null;
        });

        $middleware->handle(new Envelope(new \stdClass(), [new CompanyStamp($companyId)]), $stack);

        self::assertNotNull($seenDuringHandling);
        self::assertTrue($companyId->equals($seenDuringHandling));
        self::assertFalse($context->hasCompany(), 'The context must be cleared again after handling.');
    }

    public function testClearsEvenWhenTheNextMiddlewareThrows(): void
    {
        $context = new RequestCompanyContext();
        $middleware = new RestoreCompanyContextMiddleware($context);

        $stack = $this->stackThatCalls(static function (): void {
            throw new \RuntimeException('handler failed');
        });

        try {
            $middleware->handle(new Envelope(new \stdClass(), [new CompanyStamp(CompanyId::generate())]), $stack);
            self::fail('Expected the exception to propagate.');
        } catch (\RuntimeException) {
            // expected
        }

        self::assertFalse($context->hasCompany());
    }

    public function testLeavesAnAlreadySetContextAloneWhenThereIsNoStamp(): void
    {
        $context = new RequestCompanyContext();
        $existing = CompanyId::generate();
        $context->set($existing);
        $middleware = new RestoreCompanyContextMiddleware($context);

        $stack = $this->stackThatCalls(static function (): void {
        });

        $middleware->handle(new Envelope(new \stdClass()), $stack);

        self::assertTrue($context->hasCompany());
        self::assertTrue($existing->equals($context->companyId()));
    }

    private function stackThatCalls(\Closure $onHandle): StackInterface
    {
        $stack = $this->createMock(StackInterface::class);
        $next = new class($onHandle) implements \Symfony\Component\Messenger\Middleware\MiddlewareInterface {
            public function __construct(private readonly \Closure $onHandle)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                ($this->onHandle)();

                return $envelope;
            }
        };
        $stack->method('next')->willReturn($next);

        return $stack;
    }
}
