<?php

declare(strict_types=1);

namespace App\AtIntegration\Domain\Webservice;

use App\Shared\Domain\AtIntegration\AtCommunicableDocument;
use App\Shared\Domain\CompanyId;

/**
 * `Fatcorews.wsdl` (docs/legal/): communicating issued documents to AT's
 * e-Fatura webservice. Never throws for an AT verdict or an unreachable AT —
 * both come back as an {@see AtCommunicationOutcome}, so the caller always
 * has something to record. Does throw {@see \App\Shared\Domain\Company\AtCredentialsNotConfigured}
 * (docs/plans/phase-3.md decision 11) when the company has no AT sub-user yet.
 */
interface AtDocumentWebserviceClient
{
    /**
     * `RegisterInvoice` for SalesInvoices documents, `RegisterWork` for
     * WorkingDocuments (resolved from {@see AtCommunicableDocument::$saftSection}).
     * The outcome carries a digest of the exact request body sent.
     */
    public function register(CompanyId $companyId, AtCommunicableDocument $document): AtCommunicationOutcome;

    /**
     * `ChangeWorkStatus` — a working document's status moved on after AT
     * accepted its registration (task 2.8's `N` → `F`).
     */
    public function changeStatus(CompanyId $companyId, AtCommunicableDocument $document): AtCommunicationOutcome;
}
