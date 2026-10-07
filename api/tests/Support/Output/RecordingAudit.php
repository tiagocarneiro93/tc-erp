<?php

declare(strict_types=1);

namespace App\Tests\Support\Output;

use App\Shared\Domain\Audit\AuditLogger;

final class RecordingAudit implements AuditLogger
{
    /** @var list<array{action: string, data: array<string, mixed>}> */
    public array $entries = [];

    public function log(string $action, string $subjectType, string $subjectId, array $data, ?string $userId, ?string $apiTokenId, string $ip, string $userAgent): void
    {
        $this->entries[] = ['action' => $action, 'data' => $data];
    }
}
