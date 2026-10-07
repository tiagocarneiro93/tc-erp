<?php

declare(strict_types=1);

namespace App\Output\Domain;

final class ObjectNotFound extends \RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct(\sprintf('No object is stored under "%s".', $key));
    }
}
