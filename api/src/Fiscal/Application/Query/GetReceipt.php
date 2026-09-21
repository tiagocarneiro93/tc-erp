<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\ReceiptId;

final class GetReceipt
{
    public function __construct(public readonly ReceiptId $receiptId)
    {
    }
}
