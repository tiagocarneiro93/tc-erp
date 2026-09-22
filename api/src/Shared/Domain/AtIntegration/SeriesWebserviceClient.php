<?php

declare(strict_types=1);

namespace App\Shared\Domain\AtIntegration;

use App\Shared\Domain\CompanyId;

/**
 * Cross-module port (docs/decisions/0004's pattern): lets Fiscal's series
 * lifecycle (`ActivateSeriesHandler`/`FinishSeriesHandler`/`CancelSeriesHandler`)
 * call AT's series-communication webservice without depending on
 * `AtIntegration\Domain`/`Infrastructure`, which Deptrac forbids.
 * docs/plans/phase-3.md decision 3: synchronous, called directly from the
 * HTTP request — series registration is a low-volume, deliberate action,
 * not the per-document outbox's fire-and-forget volume.
 *
 * Never throws for an AT-side rejection (a validation error, a duplicate
 * code, …) — {@see SeriesWebserviceResult::$accepted} carries that, so the
 * caller can record the `at_communications` audit row before deciding what
 * it means. Does throw (a plain `\RuntimeException`) for a transport-level
 * failure — AT unreachable, malformed response — since there's no
 * `SeriesWebserviceResult` to sensibly construct without a real AT
 * response to read.
 */
interface SeriesWebserviceClient
{
    public function register(CompanyId $companyId, SeriesRegistration $request): SeriesWebserviceResult;

    public function finish(CompanyId $companyId, SeriesFinalization $request): SeriesWebserviceResult;

    public function cancel(CompanyId $companyId, SeriesCancellation $request): SeriesWebserviceResult;
}
