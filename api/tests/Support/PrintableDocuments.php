<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Domain\Fiscal\PrintableDocument;
use App\Shared\Domain\Fiscal\PrintableLine;
use App\Shared\Domain\Fiscal\PrintableParty;
use App\Shared\Domain\Fiscal\PrintableTaxSummary;

/**
 * Hand-built `PrintableDocument`s for the PDF unit tests (the end-to-end tests
 * use real issued documents instead).
 */
final class PrintableDocuments
{
    private function __construct()
    {
    }

    public static function invoice(
        string $type = 'FT',
        string $typeName = 'Fatura',
        string $status = 'N',
        bool $training = false,
        string $customerName = 'Cliente Um, SA',
        string $templateVersion = 'v1',
        ?string $notAnInvoiceMention = null,
    ): PrintableDocument {
        return new PrintableDocument(
            id: '0192e0f0-0000-7000-8000-000000000001',
            documentType: $type,
            documentTypeName: $typeName,
            documentNo: $type.' 2026A/1',
            atcud: 'CSDF7T5H-1',
            issueDate: new \DateTimeImmutable('2026-10-07'),
            dueDate: new \DateTimeImmutable('2026-11-06'),
            systemEntryAt: new \DateTimeImmutable('2026-10-07T10:11:12+00:00'),
            status: $status,
            statusReason: 'A' === $status ? 'Emitida por engano' : null,
            isTraining: $training,
            templateVersion: $templateVersion,
            hashMention: 'WorkingDocuments' === $typeName ? null : 'AbCd-Processado por programa certificado n.º 0000/AT',
            qrPayload: 'A:508025090*B:123456789*C:PT*D:'.$type.'*E:'.$status.'*F:20261007*G:'.$type.' 2026A/1*H:CSDF7T5H-1*I1:PT*I7:900.00*I8:207.00*N:207.00*O:1107.00*Q:AbCd*R:0000',
            notAnInvoiceMention: $notAnInvoiceMention,
            currency: 'EUR',
            globalDiscountPercent: null,
            settlementTotal: '0.00',
            netTotal: '1100.00',
            taxTotal: '207.00',
            grossTotal: '1307.00',
            paymentTerms: ['name' => '30 dias'],
            issuer: new PrintableParty('Empresa Exportadora, Lda', '508025090', 'Rua do Comércio 10', '4000-100', 'Porto', 'PT', 'geral@empresa.pt', '220000000'),
            customer: new PrintableParty($customerName, '123456789', 'Avenida da Liberdade 100', '1250-001', 'Lisboa', 'PT'),
            referencedDocumentNos: [],
            lines: [
                new PrintableLine(1, 'SKU-1', 'Widget', '2.000000', 'UN', '500.000000', '10.00', '900.000000', '23.00', 'NOR', null, null, []),
                new PrintableLine(2, 'SKU-2', 'Livro isento', '1.000000', 'UN', '200.000000', null, '200.000000', '0.00', 'ISE', 'M07', 'Isento artigo 9.º do CIVA', ['OR 2026A/7']),
            ],
            taxSummary: [
                new PrintableTaxSummary('PT', 'NOR', '23.00', '900.00', '207.00'),
                new PrintableTaxSummary('PT', 'ISE', '0.00', '200.00', '0.00'),
            ],
        );
    }
}
