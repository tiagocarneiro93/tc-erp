<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

use App\AtIntegration\Infrastructure\Soap\AtSoapHttpTransport;

/**
 * Wraps the real transport and keeps the last exchange so the test can file
 * it in the report next to AT's verdict.
 */
final class CapturingAtTransport implements AtSoapHttpTransport
{
    public ?string $lastRequest = null;
    public ?string $lastResponse = null;

    public function __construct(private readonly AtSoapHttpTransport $inner)
    {
    }

    public function post(string $url, string $envelope): string
    {
        $this->lastRequest = $envelope;
        $this->lastResponse = null;

        return $this->lastResponse = $this->inner->post($url, $envelope);
    }
}
