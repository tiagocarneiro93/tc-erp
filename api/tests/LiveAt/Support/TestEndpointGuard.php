<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

/**
 * The live suite must never reach AT's production services
 * (docs/plans/phase-3.md decision 10). Every endpoint it is configured with
 * has to be one of AT's published *test* endpoints — host
 * `servicos.portaldasfinancas.gov.pt` on a test port — or nothing runs at all
 * (a failure, not a skip: a mistyped URL must be loud).
 *
 * Sources: `at-ws-efatura-aspetos-genericos.pdf` §2.1 (test: 723 for sending
 * invoices, 725 for querying them) and `at-ws-series-aspetos-especificos.pdf`
 * §1.2.2 (test: 722; production: 422). Production e-Fatura is 423.
 */
final class TestEndpointGuard
{
    public const HOST = 'servicos.portaldasfinancas.gov.pt';

    /** @var list<int> */
    public const TEST_PORTS = [722, 723, 725];

    public static function assertTestEndpoint(string $name, string $url): void
    {
        $parts = parse_url($url);

        $scheme = \is_array($parts) ? ($parts['scheme'] ?? '') : '';
        $host = \is_array($parts) ? ($parts['host'] ?? '') : '';
        $port = \is_array($parts) ? ($parts['port'] ?? null) : null;

        if ('https' !== $scheme || self::HOST !== $host || !\in_array($port, self::TEST_PORTS, true)) {
            throw new RefusingNonTestEndpoint(\sprintf('Refusing to run: %s is "%s", which is not one of AT\'s TEST endpoints (https://%s:%s/…). The live-AT suite never talks to production.', $name, $url, self::HOST, implode('|', self::TEST_PORTS)));
        }
    }
}
