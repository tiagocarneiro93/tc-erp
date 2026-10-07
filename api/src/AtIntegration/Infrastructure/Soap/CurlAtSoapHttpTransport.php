<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Soap;

/**
 * Mutual TLS with the AT-issued client certificate
 * ({@see AtMutualTlsCertificate}), peer verification always on.
 *
 * AT answers a SOAP fault with HTTP 500 and a fault envelope as the body, so
 * a non-2xx status alone is not a transport failure: the body is returned
 * whenever there is one and the caller decides what it means.
 */
final class CurlAtSoapHttpTransport implements AtSoapHttpTransport
{
    private const CONNECT_TIMEOUT_SECONDS = 10;
    private const TOTAL_TIMEOUT_SECONDS = 60;

    public function __construct(private readonly AtMutualTlsCertificate $clientCertificate)
    {
    }

    public function post(string $url, string $envelope): string
    {
        $handle = curl_init($url);

        if (false === $handle) {
            throw new AtTransportFailed('Could not initialise the HTTP client.');
        }

        $clientCertificatePath = $this->clientCertificate->pemFilePath();

        if ('' === $clientCertificatePath) {
            throw new \LogicException('The AT client certificate path is empty.');
        }

        curl_setopt_array($handle, [
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => $envelope,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=UTF-8',
                'SOAPAction: ""',
            ],
            \CURLOPT_SSLCERT => $clientCertificatePath,
            \CURLOPT_SSL_VERIFYPEER => true,
            \CURLOPT_SSL_VERIFYHOST => 2,
            \CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            \CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT_SECONDS,
        ]);

        $body = curl_exec($handle);

        if (false === $body || '' === $body || true === $body) {
            $error = curl_error($handle);

            throw new AtTransportFailed('' !== $error ? $error : 'AT returned an empty response.');
        }

        return $body;
    }
}
