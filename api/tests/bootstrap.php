<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// `make signing-key` generates this for local dev, but a fresh checkout
// running `phpunit` directly (no docker step first) still needs a key for
// DocumentSigner tests to sign against. Test-bootstrap-only convenience —
// never app-runtime behaviour, and never how the real dev/production key
// is meant to be created (CLAUDE.md: the signing private key is never
// committed; production key handling is the owner's responsibility).
$configuredKeyPath = $_SERVER['DOCUMENT_SIGNING_KEY_PATH'] ?? $_ENV['DOCUMENT_SIGNING_KEY_PATH'] ?? 'var/signing/document-signing-dev.pem';
$signingKeyPath = dirname(__DIR__).'/'.(is_string($configuredKeyPath) ? $configuredKeyPath : 'var/signing/document-signing-dev.pem');

if (!is_file($signingKeyPath)) {
    @mkdir(dirname($signingKeyPath), 0o755, true);
    $key = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
    if (false === $key || !openssl_pkey_export($key, $pem)) {
        throw new RuntimeException('Could not generate a throwaway RSA key for tests.');
    }
    file_put_contents($signingKeyPath, $pem);
}
