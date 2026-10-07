<?php

declare(strict_types=1);

namespace App\Output\Domain;

final class InvalidStoredFileKind extends \InvalidArgumentException
{
    public function __construct(string $kind)
    {
        parent::__construct(\sprintf('"%s" is not a stored file kind (sealed_pdf|saft|attachment).', $kind));
    }
}
