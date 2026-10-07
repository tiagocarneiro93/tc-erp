<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Soap;

use App\AtIntegration\Domain\Security\AtRequestCipher;
use App\Shared\Domain\Clock\Clock;

/**
 * The `<wss:Security>` SOAP header every AT webservice requires
 * (`at-ws-series-aspetos-genericos.pdf` §4.1; byte-identical in the e-Fatura
 * manual's example, §2.1.1.3). Built as one raw XML string, wrapper element
 * included — the lesson of task 3.1's live test: a header whose wrapper is
 * missing is never recognised by AT's WS-Security processor, whatever the
 * cipher fields contain (see {@see SeriesWSClient::buildSecurityHeader()}).
 *
 * {@see SeriesWSClient} builds the same string for `ext-soap`'s `SoapHeader`;
 * it is deliberately left untouched — it is the one piece of this module
 * verified against AT's live environment.
 */
final class AtSecurityHeaderBuilder
{
    public function __construct(
        private readonly AtRequestCipher $cipher,
        private readonly Clock $clock,
    ) {
    }

    public function build(string $subuser, string $password): string
    {
        $credentials = $this->cipher->buildCredentials($password, $this->clock->now());

        return \sprintf(
            '<wss:Security xmlns:wss="http://schemas.xmlsoap.org/ws/2002/12/secext">'
            .'<wss:UsernameToken>'
            .'<wss:Username>%s</wss:Username>'
            .'<wss:Password>%s</wss:Password>'
            .'<wss:Nonce>%s</wss:Nonce>'
            .'<wss:Created>%s</wss:Created>'
            .'</wss:UsernameToken>'
            .'</wss:Security>',
            htmlspecialchars($subuser, \ENT_XML1),
            htmlspecialchars($credentials->passwordBase64, \ENT_XML1),
            htmlspecialchars($credentials->nonceBase64, \ENT_XML1),
            htmlspecialchars($credentials->createdBase64, \ENT_XML1),
        );
    }
}
