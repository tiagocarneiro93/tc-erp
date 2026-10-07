<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Application;

use App\Output\Application\EmailDocument;
use App\Output\Application\EmailDocumentHandler;
use App\Output\Domain\DocumentEmailDispatcher;
use App\Output\Domain\NoEmailRecipient;
use App\Output\Domain\PrintableDocumentNotFound;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Fiscal\PrintableDocument;
use App\Shared\Domain\Fiscal\PrintableDocumentReader;
use App\Shared\Domain\Fiscal\PrintableParty;
use App\Shared\Domain\Security\PermissionChecker;
use App\Tests\Support\Output\RecordingAudit;
use App\Tests\Support\PrintableDocuments;
use PHPUnit\Framework\TestCase;

final class EmailDocumentHandlerTest extends TestCase
{
    private CompanyId $company;
    private PrintableDocument $document;
    private bool $granted = true;
    private RecordingDispatcher $dispatcher;
    private RecordingAudit $audit;
    private EmailDocumentHandler $handler;

    protected function setUp(): void
    {
        $this->company = CompanyId::generate();
        $this->document = PrintableDocuments::invoice();
        $this->dispatcher = new RecordingDispatcher();
        $this->audit = new RecordingAudit();
        $this->handler = $this->handlerFor($this->document);
    }

    public function testItQueuesTheSendingToTheGivenRecipientsAndAuditsTheRequest(): void
    {
        ($this->handler)(new EmailDocument($this->document->id, ['a@example.pt', 'a@example.pt', 'b@example.pt'], 'Olá', 'user-1', '127.0.0.1', 'test'));

        self::assertSame([['a@example.pt', 'b@example.pt', 'Olá']], $this->dispatcher->queued, 'Duplicate addresses are collapsed.');
        self::assertSame('document.email_requested', $this->audit->entries[0]['action']);
    }

    public function testWithoutRecipientsItFallsBackToTheCustomersOwnAddress(): void
    {
        $handler = $this->handlerFor($this->withCustomerEmail('cliente@empresa.pt'));

        $handler(new EmailDocument($this->document->id, [], null, 'user-1', '', ''));

        self::assertSame([['cliente@empresa.pt', null]], $this->dispatcher->queued);
    }

    public function testWithNobodyToSendToItRefuses(): void
    {
        $this->expectException(NoEmailRecipient::class);

        ($this->handler)(new EmailDocument($this->document->id, [], null, 'user-1', '', ''));
    }

    public function testADocumentThatIsNotIssuedCannotBeSent(): void
    {
        $this->expectException(PrintableDocumentNotFound::class);

        try {
            ($this->handler)(new EmailDocument('0192e0f0-0000-7000-8000-00000000dead', ['a@example.pt'], null, 'user-1', '', ''));
        } finally {
            self::assertSame([], $this->dispatcher->queued);
        }
    }

    public function testAnIdThatIsNotEvenAUuidIsNotFoundEither(): void
    {
        $this->expectException(PrintableDocumentNotFound::class);

        ($this->handler)(new EmailDocument('nope', ['a@example.pt'], null, 'user-1', '', ''));
    }

    public function testItNeedsThePermissionToIssueDocuments(): void
    {
        $this->granted = false;

        $this->expectException(PermissionDenied::class);

        try {
            ($this->handler)(new EmailDocument($this->document->id, ['a@example.pt'], null, 'user-1', '', ''));
        } finally {
            self::assertSame([], $this->dispatcher->queued);
        }
    }

    private function withCustomerEmail(string $email): PrintableDocument
    {
        $document = $this->document;
        $customer = new PrintableParty($document->customer->name, $document->customer->taxId, null, null, null, 'PT', $email);

        return (new \ReflectionClass(PrintableDocument::class))->newInstanceArgs(array_map(
            static fn (\ReflectionProperty $p) => 'customer' === $p->getName() ? $customer : $p->getValue($document),
            (new \ReflectionClass(PrintableDocument::class))->getProperties(),
        ));
    }

    private function handlerFor(PrintableDocument $document): EmailDocumentHandler
    {
        $company = $this->company;
        $reader = new class($document) implements PrintableDocumentReader {
            public function __construct(private readonly PrintableDocument $document)
            {
            }

            public function find(CompanyId $companyId, string $documentId): ?PrintableDocument
            {
                return $documentId === $this->document->id ? $this->document : null;
            }
        };
        $permissions = new class($this) implements PermissionChecker {
            public function __construct(private readonly EmailDocumentHandlerTest $test)
            {
            }

            public function isGranted(string $permission, CompanyId $companyId): bool
            {
                return $this->test->granted() && 'documents.issue' === $permission;
            }
        };
        $context = new class($company) implements CompanyContext {
            public function __construct(private readonly CompanyId $company)
            {
            }

            public function hasCompany(): bool
            {
                return true;
            }

            public function companyId(): CompanyId
            {
                return $this->company;
            }

            public function set(CompanyId $companyId): void
            {
            }

            public function clear(): void
            {
            }
        };

        return new EmailDocumentHandler($reader, $this->dispatcher, $permissions, $context, $this->audit);
    }

    public function granted(): bool
    {
        return $this->granted;
    }
}

final class RecordingDispatcher implements DocumentEmailDispatcher
{
    /** @var list<list<string|null>> */
    public array $queued = [];

    public function dispatchAfterCommit(CompanyId $companyId, string $documentId, array $recipients, ?string $message, string $actingUserId, string $ip, string $userAgent): void
    {
        $this->queued[] = [...$recipients, $message];
    }
}
