<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Soap;

/**
 * AT issues the webservice client certificate as a `.pfx` (PKCS#12), but
 * PHP's SOAP stream context wants a PEM file (`local_cert`) — combined
 * cert+key, per PHP's own `openssl` stream wrapper docs. Converts once per
 * instance, into a `0600` temp file, deleted again in the destructor: the
 * decrypted private key never needs to touch disk for longer than one
 * request's lifetime.
 */
final class AtMutualTlsCertificate
{
    private ?string $pemFilePath = null;

    public function __construct(
        private readonly string $pfxPath,
        private readonly string $pfxPassword,
    ) {
    }

    public function pemFilePath(): string
    {
        if (null !== $this->pemFilePath) {
            return $this->pemFilePath;
        }

        $pfxContents = file_get_contents($this->pfxPath);

        if (false === $pfxContents) {
            throw new \RuntimeException(\sprintf('Could not read the AT client certificate at "%s" — check AT_WEBSERVICES_CLIENT_CERT_PATH.', $this->pfxPath));
        }

        if (!openssl_pkcs12_read($pfxContents, $certs, $this->pfxPassword) || !\is_array($certs)) {
            throw new \RuntimeException('Could not read the AT client certificate — check AT_WEBSERVICES_CLIENT_CERT_PASSWORD.');
        }

        $cert = $certs['cert'] ?? null;
        $privateKey = $certs['pkey'] ?? null;

        if (!\is_string($cert) || !\is_string($privateKey)) {
            throw new \RuntimeException('The AT client certificate is missing a "cert" or "pkey" entry.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'at-client-cert-');

        if (false === $tempPath) {
            throw new \RuntimeException('Could not create a temporary file for the AT client certificate.');
        }

        file_put_contents($tempPath, $cert.$privateKey);
        chmod($tempPath, 0600);

        return $this->pemFilePath = $tempPath;
    }

    public function __destruct()
    {
        if (null !== $this->pemFilePath && file_exists($this->pemFilePath)) {
            unlink($this->pemFilePath);
        }
    }
}
