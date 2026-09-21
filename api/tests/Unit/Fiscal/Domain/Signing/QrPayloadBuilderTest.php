<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Domain\Signing;

use App\Fiscal\Domain\Signing\QrPayloadBuilder;
use App\Fiscal\Domain\Signing\QrPayloadInput;
use App\Fiscal\Domain\Signing\QrRegionalTaxAmounts;
use App\Shared\Domain\Decimal\Money;
use PHPUnit\Framework\TestCase;

/**
 * `at-qrcode-spec.pdf` §5's four worked examples, reproduced byte-for-byte
 * — the canonical golden-file fixtures for the QR payload format.
 */
final class QrPayloadBuilderTest extends TestCase
{
    public function testExample1Fatura(): void
    {
        $input = new QrPayloadInput(
            issuerNif: '123456789',
            customerNif: '999999990',
            customerCountry: 'PT',
            documentTypeCode: 'FT',
            documentStatus: 'N',
            documentDate: new \DateTimeImmutable('2019-12-31'),
            documentNumber: 'FT AB2019/0035',
            atcud: 'CSDF7T5H0035',
            regionalTaxAmounts: [
                new QrRegionalTaxAmounts(
                    region: 'PT',
                    exemptBase: Money::fromString('12000.00'),
                    reducedBase: Money::fromString('15000.00'),
                    reducedTax: Money::fromString('900.00'),
                    intermediateBase: Money::fromString('50000.00'),
                    intermediateTax: Money::fromString('6500.00'),
                    normalBase: Money::fromString('80000.00'),
                    normalTax: Money::fromString('18400.00'),
                ),
                new QrRegionalTaxAmounts(
                    region: 'PT-AC',
                    exemptBase: Money::fromString('10000.00'),
                    reducedBase: Money::fromString('25000.56'),
                    reducedTax: Money::fromString('1000.02'),
                    intermediateBase: Money::fromString('75000.00'),
                    intermediateTax: Money::fromString('6750.00'),
                    normalBase: Money::fromString('100000.00'),
                    normalTax: Money::fromString('18000.00'),
                ),
                new QrRegionalTaxAmounts(
                    region: 'PT-MA',
                    exemptBase: Money::fromString('5000.00'),
                    reducedBase: Money::fromString('12500.00'),
                    reducedTax: Money::fromString('625.00'),
                    intermediateBase: Money::fromString('25000.00'),
                    intermediateTax: Money::fromString('3000.00'),
                    normalBase: Money::fromString('40000.00'),
                    normalTax: Money::fromString('8800.00'),
                ),
            ],
            taxNotIndicated: false,
            notSubjectToVat: Money::fromString('100.00'),
            stampDuty: Money::fromString('25.00'),
            totalTaxes: Money::fromString('64000.02'),
            grossTotal: Money::fromString('513600.58'),
            withholding: Money::fromString('100.00'),
            hashFourChars: 'kLp0',
            certificateNumber: '9999',
            otherInformation: 'TB;PT00000000000000000000000;513500.58',
        );

        self::assertSame(
            'A:123456789*B:999999990*C:PT*D:FT*E:N*F:20191231*G:FT AB2019/0035*H:CSDF7T5H0035'
            .'*I1:PT*I2:12000.00*I3:15000.00*I4:900.00*I5:50000.00*I6:6500.00*I7:80000.00*I8:18400.00'
            .'*J1:PT-AC*J2:10000.00*J3:25000.56*J4:1000.02*J5:75000.00*J6:6750.00*J7:100000.00*J8:18000.00'
            .'*K1:PT-MA*K2:5000.00*K3:12500.00*K4:625.00*K5:25000.00*K6:3000.00*K7:40000.00*K8:8800.00'
            .'*L:100.00*M:25.00*N:64000.02*O:513600.58*P:100.00*Q:kLp0*R:9999'
            .'*S:TB;PT00000000000000000000000;513500.58',
            QrPayloadBuilder::build($input),
        );
    }

    public function testExample2FaturaSimplificada(): void
    {
        $input = new QrPayloadInput(
            issuerNif: '123456789',
            customerNif: '999999990',
            customerCountry: 'PT',
            documentTypeCode: 'FS',
            documentStatus: 'N',
            documentDate: new \DateTimeImmutable('2019-08-12'),
            documentNumber: 'FS CDVF/12345',
            atcud: 'CDF7T5HD-12345',
            regionalTaxAmounts: [
                new QrRegionalTaxAmounts(
                    region: 'PT',
                    normalBase: Money::fromString('0.65'),
                    normalTax: Money::fromString('0.15'),
                ),
            ],
            taxNotIndicated: false,
            notSubjectToVat: null,
            stampDuty: null,
            totalTaxes: Money::fromString('0.15'),
            grossTotal: Money::fromString('0.80'),
            withholding: null,
            hashFourChars: 'YhGV',
            certificateNumber: '9999',
            otherInformation: 'NU;0.80',
        );

        self::assertSame(
            'A:123456789*B:999999990*C:PT*D:FS*E:N*F:20190812*G:FS CDVF/12345*H:CDF7T5HD-12345'
            .'*I1:PT*I7:0.65*I8:0.15*N:0.15*O:0.80*Q:YhGV*R:9999*S:NU;0.80',
            QrPayloadBuilder::build($input),
        );
    }

    public function testExample3FaturaProForma(): void
    {
        $input = new QrPayloadInput(
            issuerNif: '500000000',
            customerNif: '123456789',
            customerCountry: 'PT',
            documentTypeCode: 'PF',
            documentStatus: 'N',
            documentDate: new \DateTimeImmutable('2019-01-23'),
            documentNumber: 'PF G2019CB/145789',
            atcud: 'HB6FT7RV-145789',
            regionalTaxAmounts: [
                new QrRegionalTaxAmounts(
                    region: 'PT',
                    exemptBase: Money::fromString('12345.34'),
                    reducedBase: Money::fromString('12532.65'),
                    reducedTax: Money::fromString('751.96'),
                    intermediateBase: Money::fromString('52789.00'),
                    intermediateTax: Money::fromString('6862.57'),
                    normalBase: Money::fromString('32425.69'),
                    normalTax: Money::fromString('7457.91'),
                ),
            ],
            taxNotIndicated: false,
            notSubjectToVat: null,
            stampDuty: null,
            totalTaxes: Money::fromString('15072.44'),
            grossTotal: Money::fromString('125165.12'),
            withholding: null,
            hashFourChars: 'r/fY',
            certificateNumber: '9999',
            otherInformation: null,
        );

        self::assertSame(
            'A:500000000*B:123456789*C:PT*D:PF*E:N*F:20190123*G:PF G2019CB/145789*H:HB6FT7RV-145789'
            .'*I1:PT*I2:12345.34*I3:12532.65*I4:751.96*I5:52789.00*I6:6862.57*I7:32425.69*I8:7457.91'
            .'*N:15072.44*O:125165.12*Q:r/fY*R:9999',
            QrPayloadBuilder::build($input),
        );
    }

    public function testExample4DocumentoDeTransporte(): void
    {
        $input = new QrPayloadInput(
            issuerNif: '500000000',
            customerNif: '123456789',
            customerCountry: 'PT',
            documentTypeCode: 'GT',
            documentStatus: 'N',
            documentDate: new \DateTimeImmutable('2019-07-20'),
            documentNumber: 'GT G234CB/50987',
            atcud: 'GTVX4Y8B-50987',
            regionalTaxAmounts: [],
            taxNotIndicated: true,
            notSubjectToVat: null,
            stampDuty: null,
            totalTaxes: Money::fromString('0.00'),
            grossTotal: Money::fromString('0.00'),
            withholding: null,
            hashFourChars: '5uIg',
            certificateNumber: '9999',
            otherInformation: null,
        );

        self::assertSame(
            'A:500000000*B:123456789*C:PT*D:GT*E:N*F:20190720*G:GT G234CB/50987*H:GTVX4Y8B-50987'
            .'*I1:0*N:0.00*O:0.00*Q:5uIg*R:9999',
            QrPayloadBuilder::build($input),
        );
    }
}
