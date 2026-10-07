<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use App\Fiscal\Application\Query\ExportSaft;
use App\Fiscal\Application\Query\SaftExport;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * technical-scope.md §7.7: `GET /companies/{c}/saft?from=&to=` — a SAF-T (PT)
 * billing file for the period, validated against the official XSD before it
 * is sent (a file that does not validate is a 500, never a download).
 */
#[OA\Tag(name: 'SAF-T')]
final class SaftExportController
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/saft', name: 'saft_export', methods: ['GET'])]
    #[OA\Parameter(name: 'from', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'), description: 'First day of the period (inclusive).')]
    #[OA\Parameter(name: 'to', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'), description: 'Last day of the period (inclusive); same calendar year as `from`.')]
    #[OA\Response(response: 200, description: 'The SAF-T (PT) 1.04_01 billing file for the period.', content: new OA\MediaType(mediaType: 'application/xml', schema: new OA\Schema(type: 'string', format: 'binary')))]
    #[OA\Response(response: 403, description: 'The caller lacks the reports.read permission.')]
    #[OA\Response(response: 422, description: 'The period is malformed, ends before it starts or crosses New Year, or the company profile is incomplete.')]
    public function export(Request $request): BinaryFileResponse
    {
        /** @var SaftExport $export */
        $export = $this->handle(new ExportSaft(
            (string) $request->query->get('from', ''),
            (string) $request->query->get('to', ''),
        ));

        $response = new BinaryFileResponse($export->path, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $export->filename);
        $response->headers->set('X-Content-SHA256', $export->sha256);
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
