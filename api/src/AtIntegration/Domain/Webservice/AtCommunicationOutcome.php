<?php

declare(strict_types=1);

namespace App\AtIntegration\Domain\Webservice;

/**
 * What one e-Fatura webservice call resolved to, in terms of the
 * `at_communications.status` it should leave behind (technical-scope.md §7.5:
 * "permanent AT rejections (validation errors) go to `rejected`", transient
 * or technical errors are retried).
 *
 * `CodigoResposta` meanings come from `at-ws-efatura-aspetos-especificos.pdf`
 * §2.1.1.2 (`RegisterInvoice`), §2.1.4.2 (`RegisterWork`) and §2.1.5.2
 * (`ChangeWorkStatus`):
 *
 * - `0` — success.
 * - positive (1–99) — authentication/envelope errors (bad credentials, expired
 *   `Created`, malformed SOAP…). Not a verdict on the document; the user can
 *   fix credentials and a later attempt can succeed → retryable.
 * - `-97`/`-99` — "Erro de sistema. Por favor volte a tentar mais tarde" → retryable.
 * - the operation's own "O documento já foi registado pelo emitente" code
 *   (`-10` invoice, `-22` work) → treated as success: the only way to get it
 *   for a document this system sends is a repeat of a registration that
 *   already went through (e.g. the worker died after AT answered but before
 *   the answer was stored); AT reports different values separately (`-3`),
 *   which stays a rejection.
 * - every other negative code — a validation verdict on the data → rejected.
 */
final class AtCommunicationOutcome
{
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    private const ALREADY_REGISTERED_CODES = [
        'RegisterInvoice' => -10,
        'RegisterWork' => -22,
    ];

    private const SYSTEM_ERROR_CODES = [-97, -99];

    private function __construct(
        public readonly string $status,
        public readonly ?int $responseCode,
        public readonly string $responseMessage,
        public readonly ?string $requestDigest,
    ) {
    }

    /**
     * @param string|null $requestDigest SHA-256 of the exact request body sent, for the audit trail
     */
    public static function fromResponse(string $operation, int $code, string $message, ?string $requestDigest = null): self
    {
        if (0 === $code || (self::ALREADY_REGISTERED_CODES[$operation] ?? null) === $code) {
            return new self(self::STATUS_ACCEPTED, $code, $message, $requestDigest);
        }

        if ($code > 0 || \in_array($code, self::SYSTEM_ERROR_CODES, true)) {
            return new self(self::STATUS_FAILED, $code, $message, $requestDigest);
        }

        return new self(self::STATUS_REJECTED, $code, $message, $requestDigest);
    }

    /**
     * A call that never produced an AT verdict: AT unreachable, timeout, TLS
     * failure, a response that isn't a recognisable e-Fatura answer….
     */
    public static function transportFailure(string $message, ?string $requestDigest = null): self
    {
        return new self(self::STATUS_FAILED, null, $message, $requestDigest);
    }

    public function isAccepted(): bool
    {
        return self::STATUS_ACCEPTED === $this->status;
    }

    public function isRetryable(): bool
    {
        return self::STATUS_FAILED === $this->status;
    }
}
