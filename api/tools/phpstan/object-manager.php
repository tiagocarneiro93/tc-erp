<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/../../.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', false);
$kernel->boot();

/** @var \Doctrine\Persistence\ManagerRegistry $registry */
$registry = $kernel->getContainer()->get('doctrine');

return $registry->getManager();
