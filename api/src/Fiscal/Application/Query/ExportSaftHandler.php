<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\Exception\CompanyProfileMissing;
use App\Fiscal\Domain\Exception\InvalidSaftPeriod;
use App\Fiscal\Domain\Saft\SaftExportPeriod;
use App\Fiscal\Domain\Saft\SaftFileGenerator;
use App\Fiscal\Domain\Saft\SaftSchemaValidator;
use App\Fiscal\Domain\Saft\SaftValidationFailed;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Company\CompanyFiscalIdentityProvider;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * technical-scope.md §7.7: generate, then validate against the official XSD
 * *before* offering the file (a file AT would reject is never handed out),
 * then hand it over. Runs on `query.bus`, whose `doctrine_transaction`
 * middleware gives the whole generation one consistent read transaction with
 * the company's RLS scope.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class ExportSaftHandler
{
    public function __construct(
        private readonly SaftFileGenerator $generator,
        private readonly SaftSchemaValidator $validator,
        private readonly CompanyFiscalIdentityProvider $companies,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ExportSaft $query): SaftExport
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('reports.read', $companyId)) {
            throw new PermissionDenied();
        }

        $period = new SaftExportPeriod($this->parseDate($query->from, 'from'), $this->parseDate($query->to, 'to'));
        $company = $this->companies->forCompany($companyId) ?? throw new CompanyProfileMissing();

        $path = tempnam(sys_get_temp_dir(), 'saft-');

        if (false === $path) {
            throw new \RuntimeException('Could not create a temporary file for the SAF-T export.');
        }

        try {
            $summary = $this->generator->generate($companyId, $company, $period, $this->clock->now(), $path);

            $errors = $this->validator->validate($path);

            if ([] !== $errors) {
                throw new SaftValidationFailed($errors);
            }

            $sha256 = hash_file('sha256', $path);

            if (false === $sha256) {
                throw new \RuntimeException('Could not hash the generated SAF-T file.');
            }

            return new SaftExport(
                $path,
                \sprintf('SAFT_%s_%s_%s.xml', $company->nif, $period->start->format('Ymd'), $period->end->format('Ymd')),
                (int) filesize($path),
                $sha256,
                $summary,
            );
        } catch (\Throwable $e) {
            @unlink($path);

            throw $e;
        }
    }

    private function parseDate(string $value, string $parameter): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw InvalidSaftPeriod::malformedDate($parameter);
        }

        return $date;
    }
}
