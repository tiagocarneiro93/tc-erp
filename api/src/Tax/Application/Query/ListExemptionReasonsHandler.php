<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Tax\Domain\ExemptionReason;
use App\Tax\Domain\ExemptionReasonRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListExemptionReasonsHandler
{
    public function __construct(
        private readonly ExemptionReasonRepository $reasons,
    ) {
    }

    /**
     * @return list<ExemptionReason>
     */
    public function __invoke(ListExemptionReasons $query): array
    {
        return $this->reasons->findAll($query->asOf);
    }
}
