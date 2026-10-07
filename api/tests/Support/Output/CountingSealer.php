<?php

declare(strict_types=1);

namespace App\Tests\Support\Output;

use App\Output\Domain\ElectronicSealer;
use App\Output\Domain\SealingFailed;
use App\Shared\Domain\CompanyId;

final class CountingSealer implements ElectronicSealer
{
    public int $calls = 0;
    public bool $failing = false;

    public function seal(CompanyId $companyId, string $pdf): string
    {
        ++$this->calls;

        if ($this->failing) {
            throw new SealingFailed('provider down');
        }

        return 'sealed('.$pdf.')';
    }
}
