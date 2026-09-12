<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parties\Domain;

use App\Parties\Domain\Customer;
use App\Parties\Domain\CustomerId;
use App\Parties\Domain\Exception\FinalConsumerCustomerIsProtected;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    public function testCreateStartsActiveWithMatchingCreatedAndUpdatedAt(): void
    {
        $now = new \DateTimeImmutable('2026-01-01');
        $customer = $this->customer($now);

        self::assertTrue($customer->active());
        self::assertEquals($now, $customer->createdAt());
        self::assertEquals($now, $customer->updatedAt());
    }

    public function testUpdateChangesFieldsAndBumpsUpdatedAtButNotCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01');
        $customer = $this->customer($createdAt);

        $updatedAt = new \DateTimeImmutable('2026-02-01');
        $customer->update('C002', '502757191', 'New Name', 'New Address', '2000-000', 'Lisboa', 'PT', 'new@example.test', '+351210000000', 'a-payment-terms-id', $updatedAt);

        self::assertSame('C002', $customer->code());
        self::assertSame('New Name', $customer->name());
        self::assertSame('a-payment-terms-id', $customer->paymentTermsId());
        self::assertEquals($createdAt, $customer->createdAt());
        self::assertEquals($updatedAt, $customer->updatedAt());
    }

    public function testDeactivateSetsActiveFalseAndBumpsUpdatedAt(): void
    {
        $customer = $this->customer(new \DateTimeImmutable('2026-01-01'));

        $deactivatedAt = new \DateTimeImmutable('2026-03-01');
        $customer->deactivate($deactivatedAt);

        self::assertFalse($customer->active());
        self::assertEquals($deactivatedAt, $customer->updatedAt());
    }

    public function testUpdatingTheFinalConsumerCustomerIsRejected(): void
    {
        $customer = $this->finalConsumer(new \DateTimeImmutable('2026-01-01'));

        $this->expectException(FinalConsumerCustomerIsProtected::class);

        $customer->update('CF', '999999990', 'Renamed', null, null, null, 'PT', null, null, null, new \DateTimeImmutable('2026-02-01'));
    }

    public function testDeactivatingTheFinalConsumerCustomerIsRejected(): void
    {
        $customer = $this->finalConsumer(new \DateTimeImmutable('2026-01-01'));

        $this->expectException(FinalConsumerCustomerIsProtected::class);

        $customer->deactivate(new \DateTimeImmutable('2026-02-01'));
    }

    private function customer(\DateTimeImmutable $now): Customer
    {
        return Customer::create(
            CustomerId::generate(),
            CompanyId::generate(),
            'C001',
            '502757191',
            'Original Name',
            null,
            null,
            null,
            'PT',
            null,
            null,
            null,
            false,
            $now,
        );
    }

    private function finalConsumer(\DateTimeImmutable $now): Customer
    {
        return Customer::create(
            CustomerId::generate(),
            CompanyId::generate(),
            'CF',
            '999999990',
            'Consumidor final',
            null,
            null,
            null,
            'PT',
            null,
            null,
            null,
            true,
            $now,
        );
    }
}
