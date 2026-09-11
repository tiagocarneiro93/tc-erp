<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use Nelmio\ApiDocBundle\Render\RenderOpenApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * CLAUDE.md task 0.11's CI check: `api/openapi.json` must be exactly what
 * `make openapi` (`nelmio:apidoc:dump`) would produce right now — this
 * fails the moment an endpoint changes without the spec being regenerated
 * and committed.
 */
final class OpenApiSpecIsUpToDateTest extends KernelTestCase
{
    public function testTheCommittedSpecMatchesWhatWouldBeGeneratedNow(): void
    {
        self::bootKernel();
        /** @var RenderOpenApi $renderer */
        $renderer = self::getContainer()->get('nelmio_api_doc.render_docs');

        $generated = $renderer->render(RenderOpenApi::JSON, 'default', ['no-pretty' => false]);
        $committed = file_get_contents(\dirname(__DIR__, 3).'/openapi.json');

        self::assertNotFalse($committed, 'api/openapi.json is missing — run `make openapi`.');
        self::assertJsonStringEqualsJsonString(
            $committed,
            $generated,
            'api/openapi.json is out of date — an endpoint changed without running `make openapi`.',
        );
    }
}
