<?php

declare(strict_types=1);

namespace App\Tests\Functional\Output;

use App\Output\Infrastructure\Sealing\FakeElectronicSealer;
use App\Tests\Support\FiscalFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;

/**
 * docs/plans/phase-3.md task 3.7 through the real HTTP stack, the real
 * Messenger handlers and real PostgreSQL/MinIO: the request is queued, the
 * worker seals once and mails, and nothing is ever re-sealed.
 */
final class EmailDocumentTest extends WebTestCase
{
    use FiscalFlowHelpers;

    public function testTheSealedPdfIsMailedAsAnAttachmentAndLoggedOnce(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');
        $this->consumeAsyncMessages(); // the AT wake-up

        $this->send($client, $companyId, $documentId, 'send-1', ['recipients' => ['cliente@example.pt'], 'message' => 'Obrigado pela preferência.']);

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertEmailCount(0, message: 'Nothing is mailed inside the request.');
        self::assertSame(1, $this->consumeAsyncMessages());
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame(['cliente@example.pt'], array_map(static fn ($a) => $a->getAddress(), $email->getTo()));
        self::assertStringContainsString('FT 2026A/1', $email->getSubject() ?? '');
        self::assertStringContainsString('Obrigado pela preferência.', (string) $email->getTextBody());

        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertSame('FT_2026A_1.pdf', $attachments[0]->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'));
        $pdf = $attachments[0]->getBody();
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString(FakeElectronicSealer::MARKER, $pdf);

        $files = $this->connection($companyId)->fetchAllAssociative("SELECT sha256 FROM stored_files WHERE company_id = ? AND kind = 'sealed_pdf'", [$companyId]);
        self::assertSame([['sha256' => hash('sha256', $pdf)]], $files, 'The file mailed is the file archived.');
        self::assertSame(
            [['kind' => 'email', 'copy_label' => 'Original']],
            $this->connection($companyId)->fetchAllAssociative('SELECT kind, copy_label FROM document_prints WHERE company_id = ?', [$companyId]),
        );
    }

    public function testMailingTheSameDocumentAgainSendsTheSameStoredFileWithoutSealingAnotherOne(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');
        $this->consumeAsyncMessages();

        // Each request boots a fresh kernel (and with it a fresh in-memory queue and mail log), so collect after each.
        $sent = [];

        foreach (['a@example.pt', 'b@example.pt'] as $n => $recipient) {
            $this->send($client, $companyId, $documentId, 'send-'.$n, ['recipients' => [$recipient]]);
            self::assertSame(1, $this->consumeAsyncMessages());
            $email = self::getMailerMessage();
            self::assertInstanceOf(Email::class, $email);
            $sent[] = $email;
        }

        self::assertSame($sent[0]->getAttachments()[0]->getBody(), $sent[1]->getAttachments()[0]->getBody());
        self::assertCount(1, $this->connection($companyId)->fetchAllAssociative("SELECT 1 FROM stored_files WHERE company_id = ? AND kind = 'sealed_pdf'", [$companyId]));
        self::assertCount(1, $this->connection($companyId)->fetchAllAssociative("SELECT 1 FROM document_prints WHERE company_id = ? AND kind = 'email'", [$companyId]));
    }

    public function testARepeatedRequestWithTheSameIdempotencyKeyQueuesOnlyOnce(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');
        $this->consumeAsyncMessages();

        $this->send($client, $companyId, $documentId, 'same-key', ['recipients' => ['a@example.pt']]);
        self::assertSame(1, $this->consumeAsyncMessages());

        $this->send($client, $companyId, $documentId, 'same-key', ['recipients' => ['a@example.pt']]);

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertSame(0, $this->consumeAsyncMessages(), 'The replay is answered from the stored response and queues nothing.');
    }

    public function testWithoutRecipientsAndWithoutACustomerAddressThereIsNobodyToSendTo(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');
        $this->consumeAsyncMessages();

        $this->send($client, $companyId, $documentId, 'send-1', []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->consumeAsyncMessages());
    }

    public function testAnInvalidAddressIsRefusedBeforeAnythingIsQueued(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');
        $this->consumeAsyncMessages();

        $this->send($client, $companyId, $documentId, 'send-1', ['recipients' => ['not-an-address']]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->consumeAsyncMessages());
    }

    public function testADraftCannotBeSent(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $this->send($client, $companyId, '0192e0f0-0000-7000-8000-00000000dead', 'send-1', ['recipients' => ['a@example.pt']]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(0, $this->consumeAsyncMessages());
    }

    public function testSendingNeedsAnIdempotencyKey(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/send", server: self::HEADERS, content: '{"recipients":["a@example.pt"]}');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function send(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $companyId, string $documentId, string $key, array $body): void
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/send", server: self::HEADERS + ['HTTP_Idempotency-Key' => $key], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }
}
