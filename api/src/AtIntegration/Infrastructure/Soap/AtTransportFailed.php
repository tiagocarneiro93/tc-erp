<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Soap;

/**
 * AT could not be reached or did not answer (DNS, TLS handshake, timeout…).
 * Never carries credentials or the request body — only the cURL error.
 */
final class AtTransportFailed extends \RuntimeException
{
}
