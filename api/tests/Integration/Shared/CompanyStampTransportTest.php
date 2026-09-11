<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Messenger\CompanyStamp;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * technical-scope.md §5.4, CLAUDE.md task 0.9: proves a CompanyStamp
 * actually survives a transport round-trip (config/packages/messenger.yaml
 * swaps the async transport for `in-memory://` `when@test`), not just that
 * {@see \App\Shared\Infrastructure\Messenger\RestoreCompanyContextMiddleware}
 * reads one correctly in-process (covered by its own unit test).
 */
final class CompanyStampTransportTest extends KernelTestCase
{
    public function testACompanyStampSurvivesTheAsyncTransport(): void
    {
        self::bootKernel();
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');
        $companyId = CompanyId::generate();

        $transport->send(new Envelope(new \stdClass(), [new CompanyStamp($companyId)]));

        $envelopes = iterator_to_array($transport->get());
        self::assertCount(1, $envelopes);

        $stamp = $envelopes[0]->last(CompanyStamp::class);
        self::assertInstanceOf(CompanyStamp::class, $stamp);
        self::assertSame($companyId->toString(), $stamp->companyId);
    }
}
