<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Exception\ExemptionReasonRequired;
use App\Catalog\Domain\Exception\InvalidExemptionReasonCode;
use App\Catalog\Domain\Exception\InvalidProductType;
use App\Catalog\Domain\Exception\InvalidTaxRateId;
use App\Catalog\Domain\Exception\InvalidUnitCode;
use App\Catalog\Domain\Exception\ProductDescriptionIsLocked;
use App\Catalog\Domain\Exception\ProductFamilyNotFound;
use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductFamilyRepository;
use App\Catalog\Domain\ProductRepository;
use App\Catalog\Domain\UnitRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Fiscal\ProductHasIssuedDocuments;
use App\Shared\Domain\Security\PermissionChecker;
use App\Shared\Domain\Tax\ExemptionReasonExistenceChecker;
use App\Shared\Domain\Tax\TaxRateExemptionChecker;
use App\Shared\Domain\Tax\TaxRateExistenceChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Despacho 8632/2014 §3.3.3–3.3.5, docs/plans/phase-2.md task 2.3: once a
 * product has been referenced by at least one issued document,
 * `description` locks — checked here, not in `Product::update()` itself,
 * for the same Deptrac-driven reason as `UpdateCustomerHandler`.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class UpdateProductHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductFamilyRepository $families,
        private readonly UnitRepository $units,
        private readonly TaxRateExistenceChecker $taxRates,
        private readonly TaxRateExemptionChecker $taxRateExemption,
        private readonly ExemptionReasonExistenceChecker $exemptionReasons,
        private readonly ProductHasIssuedDocuments $issuedDocuments,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(UpdateProduct $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $product = $this->products->find($companyId, $command->productId);

        if (null === $product) {
            throw new ProductNotFound();
        }

        if (!Product::isValidType($command->type)) {
            throw new InvalidProductType($command->type);
        }

        if (!$this->isKnownUnit($command->unitCode)) {
            throw new InvalidUnitCode($command->unitCode);
        }

        if (null !== $command->familyId && null === $this->families->find($companyId, $command->familyId)) {
            throw new ProductFamilyNotFound();
        }

        if (!$this->taxRates->exists($command->taxRateId)) {
            throw new InvalidTaxRateId($command->taxRateId);
        }

        if (null !== $command->exemptionReasonCode && !$this->exemptionReasons->exists($command->exemptionReasonCode)) {
            throw new InvalidExemptionReasonCode($command->exemptionReasonCode);
        }

        if (null === $command->exemptionReasonCode && $this->taxRateExemption->isExempt($command->taxRateId)) {
            throw new ExemptionReasonRequired();
        }

        if ($command->description !== $product->description() && $this->issuedDocuments->forProduct($companyId, $product->id()->toString())) {
            throw new ProductDescriptionIsLocked();
        }

        $product->update(
            $command->code,
            $command->description,
            $command->type,
            $command->unitCode,
            $command->barcode,
            $command->familyId,
            $command->taxRateId,
            $command->exemptionReasonCode,
            $command->trackStock,
        );
        $this->products->save($product);

        $this->auditLogger->log(
            'product.updated',
            'Product',
            $command->productId->toString(),
            ['code' => $command->code, 'description' => $command->description],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }

    private function isKnownUnit(string $unitCode): bool
    {
        foreach ($this->units->findAll() as $unit) {
            if ($unit->code() === $unitCode) {
                return true;
            }
        }

        return false;
    }
}
