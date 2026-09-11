<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReportsDatabaseAndRedis(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/health');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","checks":{"database":true,"redis":true}}',
            (string) $client->getResponse()->getContent(),
        );
    }
}
