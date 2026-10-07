<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Fake;

use App\AtIntegration\Domain\Efatura\EFaturaRequestBuilder;
use App\AtIntegration\Domain\Webservice\AtCommunicationOutcome;
use App\AtIntegration\Domain\Webservice\AtDocumentWebserviceClient;
use App\Shared\Domain\AtIntegration\AtCommunicableDocument;
use App\Shared\Domain\CompanyId;

/**
 * Bound in place of {@see \App\AtIntegration\Infrastructure\Soap\EFaturaWSClient}
 * for `APP_ENV=test` (`config/services_test.yaml`), same fake/real split as
 * {@see FakeSeriesWebserviceClient}. It still builds the real request body
 * with {@see EFaturaRequestBuilder} — so every functional test that issues a
 * document also proves the builder copes with that document's stored data —
 * but answers from a script instead of calling AT.
 *
 * State is static on purpose: `KernelBrowser` reboots the kernel (and with it
 * every service instance) before each request, so instance state would not
 * survive from "issue a document over HTTP" to "consume its message in the
 * test". Tests must call {@see self::reset()} in `setUp()`.
 */
final class FakeAtDocumentWebserviceClient implements AtDocumentWebserviceClient
{
    /** @var list<AtCommunicationOutcome> */
    private static array $script = [];

    /** @var list<array{operation: string, documentNo: string, body: string}> */
    private static array $calls = [];

    public function __construct(private readonly EFaturaRequestBuilder $requests)
    {
    }

    public static function reset(): void
    {
        self::$script = [];
        self::$calls = [];
    }

    /**
     * The next call answers with this outcome (FIFO); once the script is
     * empty every call is accepted.
     */
    public static function answerNextWith(AtCommunicationOutcome $outcome): void
    {
        self::$script[] = $outcome;
    }

    /**
     * @return list<array{operation: string, documentNo: string, body: string}>
     */
    public static function calls(): array
    {
        return self::$calls;
    }

    public function register(CompanyId $companyId, AtCommunicableDocument $document): AtCommunicationOutcome
    {
        $operation = $this->requests->operationFor($document);

        return $this->answer($operation, $document, $this->requests->buildRegister($document));
    }

    public function changeStatus(CompanyId $companyId, AtCommunicableDocument $document): AtCommunicationOutcome
    {
        return $this->answer('ChangeWorkStatus', $document, $this->requests->buildChangeWorkStatus($document));
    }

    private function answer(string $operation, AtCommunicableDocument $document, string $body): AtCommunicationOutcome
    {
        self::$calls[] = ['operation' => $operation, 'documentNo' => $document->documentNo, 'body' => $body];

        $scripted = array_shift(self::$script);

        return $scripted ?? AtCommunicationOutcome::fromResponse($operation, 0, 'Operação efetuada com sucesso (fake, test environment).', hash('sha256', $body));
    }
}
