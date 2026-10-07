<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The register-user / create-company / activate-series / issue-document
 * plumbing the AT-communication functional tests need, kept in one place
 * instead of copied into a ninth test class. For use in a `WebTestCase`.
 *
 * @mixin \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
 */
trait FiscalFlowHelpers
{
    /** @var array<string, string> */
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    /**
     * The document outbox only (`invoice`, `document_status`) — series
     * activation writes its own `series_register` audit rows alongside.
     *
     * @return list<array<string, mixed>>
     */
    private function atCommunications(string $companyId): array
    {
        $connection = $this->connection($companyId);

        /** @var list<array<string, mixed>> $rows */
        $rows = $connection->fetchAllAssociative("SELECT * FROM at_communications WHERE company_id = ? AND kind IN ('invoice', 'document_status') ORDER BY created_at, id", [$companyId]);

        return $rows;
    }

    /**
     * A fresh active series for `$type`, then one document of that type issued into it.
     */
    private function issueOfType(KernelBrowser $client, string $companyId, string $type, string $seriesCode = '2026A'): string
    {
        $extraPayload = [];

        if (\in_array($type, ['NC', 'ND'], true)) {
            // A credit/debit note must reference the invoice it rectifies (Despacho 8632/2014 §3.3).
            $invoiceSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026R');
            $this->issueDocument($client, $companyId, $invoiceSeriesId, 'FT');
            $extraPayload = ['references' => [['referenced_document_no' => 'FT 2026R/1', 'reason' => 'Rectification']]];
        }

        return $this->issueDocument($client, $companyId, $this->createActiveSeries($client, $companyId, $type, $seriesCode), $type, extraPayload: $extraPayload);
    }

    /**
     * The sweeper is cross-company by design, so rows left `pending` by
     * earlier tests (whose in-memory wake-ups died with their kernels) would
     * be swept into this one. Truncating needs the owner connection: the
     * runtime role is — rightly — scoped to one company at a time.
     */
    private function resetAtOutbox(): void
    {
        static::bootKernel();
        /** @var Connection $owner */
        $owner = static::getContainer()->get('doctrine.dbal.migrations_connection');
        $owner->executeStatement('TRUNCATE at_communications');
        static::ensureKernelShutdown();
    }

    private function connection(string $companyId): Connection
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));

        return $connection;
    }

    /**
     * What `messenger:consume async` does: take every queued envelope and
     * hand it to the bus it was sent for, marked as received so the send
     * middleware does not just route it straight back to the transport.
     *
     * @return int how many messages were handled
     */
    private function consumeAsyncMessages(): int
    {
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('messenger.routable_message_bus');
        $handled = 0;

        foreach ($transport->get() as $envelope) {
            $bus->dispatch($envelope->with(new ReceivedStamp('async')));
            $transport->ack($envelope);
            ++$handled;
        }

        return $handled;
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @param array<string, mixed>       $extraPayload merged into the draft payload (e.g. `references` for a credit note)
     */
    private function issueDocument(KernelBrowser $client, string $companyId, string $seriesId, string $documentType, array $lines = [], string $idempotencyKey = '', array $extraPayload = []): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => $documentType,
            'payload' => [
                'series_id' => $seriesId,
                'pricing_mode' => 'net',
                'rounding_method' => 'per_line',
                'date' => '2026-01-01',
                'lines' => [] === $lines ? [$this->widgetLine()] : $lines,
            ] + $extraPayload,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $draft */
        $draft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draft['id']}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => '' !== $idempotencyKey ? $idempotencyKey : 'issue-'.$draft['id']], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $document */
        $document = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $document['id'];
    }

    /**
     * @return array<string, string>
     */
    private function widgetLine(string $quantity = '1', string $unitPrice = '10.00'): array
    {
        return ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => $quantity, 'unit_price' => $unitPrice, 'tax_region' => 'PT', 'tax_code' => 'NOR'];
    }

    private function createActiveSeries(KernelBrowser $client, string $companyId, string $documentType, string $code, bool $training = false): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/series", server: self::HEADERS, content: json_encode([
            'document_type' => $documentType,
            'code' => $code,
            'is_training' => $training,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$created['id']}/activate", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        return $created['id'];
    }

    private function createCompany(KernelBrowser $client, string $legalName = 'A Company Lda'): string
    {
        $client->request('POST', '/api/v1/companies', server: self::HEADERS, content: json_encode([
            'nif' => $this->uniqueNif(),
            'legal_name' => $legalName,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $created['id'];
    }

    private function registerAndLogIn(KernelBrowser $client): UserId
    {
        $email = \sprintf('at-flow-%s@example.test', bin2hex(random_bytes(8)));
        $password = 'owner-password';

        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $user = User::register(UserId::generate(), $email, 'AT Flow User', $hasher->hash($password), new \DateTimeImmutable());
        $user->changePassword($hasher->hash($password), new \DateTimeImmutable());
        $users->save($user);

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        return $user->id();
    }

    private function uniqueNif(): string
    {
        do {
            $prefix = (string) random_int(10_000_000, 99_999_999);
            $sum = 0;
            for ($position = 0; $position < 8; ++$position) {
                $sum += (int) $prefix[$position] * (9 - $position);
            }
            $remainder = $sum % 11;
            $checkDigit = $remainder < 2 ? 0 : 11 - $remainder;
            $nif = $prefix.$checkDigit;
        } while (!Nif::isValid($nif));

        return $nif;
    }
}
