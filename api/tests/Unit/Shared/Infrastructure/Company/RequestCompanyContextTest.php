<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Company;

use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use PHPUnit\Framework\TestCase;

final class RequestCompanyContextTest extends TestCase
{
    public function testHasNoCompanyByDefault(): void
    {
        $context = new RequestCompanyContext();

        self::assertFalse($context->hasCompany());
    }

    public function testCompanyIdThrowsWhenNoneIsSet(): void
    {
        $context = new RequestCompanyContext();

        $this->expectException(\LogicException::class);

        $context->companyId();
    }

    public function testSetThenClear(): void
    {
        $context = new RequestCompanyContext();
        $companyId = CompanyId::generate();

        $context->set($companyId);
        self::assertTrue($context->hasCompany());
        self::assertTrue($companyId->equals($context->companyId()));

        $context->clear();
        self::assertFalse($context->hasCompany());
    }

    public function testResetClearsTheCompany(): void
    {
        $context = new RequestCompanyContext();
        $context->set(CompanyId::generate());

        $context->reset();

        self::assertFalse($context->hasCompany());
    }
}
