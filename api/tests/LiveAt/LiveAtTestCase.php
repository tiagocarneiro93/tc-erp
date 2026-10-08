<?php

declare(strict_types=1);

namespace App\Tests\LiveAt;

use App\Tests\LiveAt\Support\LiveAtHarness;
use PHPUnit\Framework\TestCase;

/**
 * Base of every live-AT test: builds the harness from the environment, skips
 * (listing everything that is missing) when the suite is not configured, and
 * fails outright when an endpoint is not an AT test endpoint.
 */
abstract class LiveAtTestCase extends TestCase
{
    protected function harness(): LiveAtHarness
    {
        /** @var array<string, mixed> $env */
        $env = $_SERVER + $_ENV;
        [$harness, $problems] = LiveAtHarness::tryCreate($env, \dirname(__DIR__, 2));

        if (null === $harness) {
            self::markTestSkipped("The live-AT suite is not configured — set these in api/.env.test.local (see tests/LiveAt/README.md):\n - ".implode("\n - ", $problems));
        }

        return $harness;
    }

    protected function requireSoapExtension(): void
    {
        if (!\extension_loaded('soap')) {
            self::markTestSkipped('The series webservice needs ext-soap, which this PHP does not have.');
        }
    }
}
