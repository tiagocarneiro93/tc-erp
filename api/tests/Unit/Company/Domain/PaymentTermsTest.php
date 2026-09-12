<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Domain;

use App\Company\Domain\PaymentTerms;
use App\Company\Domain\PaymentTermsId;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

final class PaymentTermsTest extends TestCase
{
    public function testCreateStartsActiveWithTheGivenFields(): void
    {
        $paymentTerms = PaymentTerms::create(PaymentTermsId::generate(), CompanyId::generate(), 'Pronto Pagamento', 0, true);

        self::assertSame('Pronto Pagamento', $paymentTerms->name());
        self::assertSame(0, $paymentTerms->days());
        self::assertTrue($paymentTerms->isDefault());
        self::assertTrue($paymentTerms->active());
    }

    public function testUpdateReplacesFieldsWithoutChangingIdentity(): void
    {
        $id = PaymentTermsId::generate();
        $companyId = CompanyId::generate();
        $paymentTerms = PaymentTerms::create($id, $companyId, 'Pronto Pagamento', 0, false);

        $paymentTerms->update('30 dias', 30, true);

        self::assertSame($id, $paymentTerms->id());
        self::assertSame($companyId, $paymentTerms->companyId());
        self::assertSame('30 dias', $paymentTerms->name());
        self::assertSame(30, $paymentTerms->days());
        self::assertTrue($paymentTerms->isDefault());
    }

    public function testUnmarkAsDefaultClearsTheFlag(): void
    {
        $paymentTerms = PaymentTerms::create(PaymentTermsId::generate(), CompanyId::generate(), 'Pronto Pagamento', 0, true);

        $paymentTerms->unmarkAsDefault();

        self::assertFalse($paymentTerms->isDefault());
    }

    public function testDeactivateClearsActive(): void
    {
        $paymentTerms = PaymentTerms::create(PaymentTermsId::generate(), CompanyId::generate(), 'Pronto Pagamento', 0, true);

        $paymentTerms->deactivate();

        self::assertFalse($paymentTerms->active());
    }
}
