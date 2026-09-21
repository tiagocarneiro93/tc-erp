<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Signing;

/**
 * Despacho 8632/2014 §2.1.1/§6.2: signs the {@see SigningMessage} with the
 * producer's RSA private key. The single seam between the pure `Domain`
 * message/format builders in this namespace and the concrete
 * RSA-1024/SHA-1 mechanics (`Fiscal\Infrastructure\Signing\OpenSslDocumentSigner`),
 * so a future key-rotation or HSM-backed implementation only needs a new
 * adapter here.
 */
interface DocumentSigner
{
    /**
     * @param string $message a {@see SigningMessage::build()} result
     */
    public function sign(string $message): SignedHash;
}
