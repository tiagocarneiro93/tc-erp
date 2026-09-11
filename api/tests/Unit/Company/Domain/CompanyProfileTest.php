<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Domain;

use App\Company\Domain\CompanyProfile;
use App\Company\Domain\Exception\InvalidFiscalRegion;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;
use PHPUnit\Framework\TestCase;

final class CompanyProfileTest extends TestCase
{
    public function testCreateDefaultPrefillsTheNifAndLegalNameFromTheRegistrationEvent(): void
    {
        $nif = Nif::fromString('502757191');
        $profile = CompanyProfile::createDefault(CompanyId::generate(), $nif, 'Acme, Lda.');

        self::assertTrue($profile->nif()->equals($nif));
        self::assertSame('Acme, Lda.', $profile->legalName());
        self::assertSame('PT', $profile->country());
        self::assertSame('PT', $profile->fiscalRegion());
        self::assertSame('normal', $profile->vatRegime());
        self::assertFalse($profile->cashVat());
        self::assertNull($profile->commercialName());
    }

    public function testUpdateProfileRejectsAnUnknownFiscalRegion(): void
    {
        $profile = CompanyProfile::createDefault(CompanyId::generate(), Nif::fromString('502757191'), 'Acme, Lda.');

        $this->expectException(InvalidFiscalRegion::class);

        $profile->updateProfile(
            Nif::fromString('502757191'),
            'Acme, Lda.',
            null,
            null,
            null,
            null,
            'PT',
            null,
            null,
            null,
            null,
            null,
            'PT-XX',
            'normal',
            false,
        );
    }

    public function testUpdateProfileAcceptsEveryValidFiscalRegion(): void
    {
        $profile = CompanyProfile::createDefault(CompanyId::generate(), Nif::fromString('502757191'), 'Acme, Lda.');

        foreach (['PT', 'PT-AC', 'PT-MA'] as $region) {
            $profile->updateProfile(
                Nif::fromString('502757191'),
                'Acme, Lda.',
                null,
                null,
                null,
                null,
                'PT',
                null,
                null,
                null,
                null,
                null,
                $region,
                'normal',
                false,
            );

            self::assertSame($region, $profile->fiscalRegion());
        }
    }
}
