<?php

declare(strict_types=1);

namespace App\Output\UI\Http;

use App\Output\Application\RenderDocumentPdf;
use App\Output\Application\RenderedDocumentPdf;
use App\Output\Domain\DocumentPrintKind;
use App\Shared\Domain\Security\CurrentActorId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * technical-scope.md §7.8: the PDF of an issued document, rendered on demand
 * from stored data. A GET that writes — deliberately: every hand-out is logged
 * in `document_prints` (and decides the original/copy label), which is the
 * point of the endpoint, not a side effect to hide behind POST.
 */
#[OA\Tag(name: 'Documents')]
final class DocumentPdfController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly CurrentActorId $currentActorId,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/api/v1/companies/{companyId}/documents/{documentId}/pdf', name: 'documents_pdf', methods: ['GET'])]
    #[OA\Parameter(name: 'kind', in: 'query', schema: new OA\Schema(type: 'string', enum: ['download', 'print'], default: 'download'), description: 'How it will be used: `download` (attachment) or `print` (inline). Both are logged and both count as handing the document out.')]
    #[OA\Response(response: 200, description: 'The PDF. `X-Copy-Label` says whether it is the original or which copy.', content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary')))]
    #[OA\Response(response: 403, description: 'The caller lacks the documents.read permission.')]
    #[OA\Response(response: 404, description: 'No such issued document (a draft is not a document).')]
    public function pdf(string $documentId, Request $request): Response
    {
        $kind = 'print' === $request->query->get('kind') ? DocumentPrintKind::Print : DocumentPrintKind::Download;

        /** @var RenderedDocumentPdf $rendered */
        $rendered = $this->handle(new RenderDocumentPdf(
            $documentId,
            $kind,
            $this->currentActorId->id(),
            $request->getClientIp() ?? '',
            $request->headers->get('User-Agent', ''),
        ));

        $response = new Response($rendered->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'X-Copy-Label' => $rendered->copyLabel,
            'Cache-Control' => 'private, no-store',
        ]);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            DocumentPrintKind::Print === $kind ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $rendered->filename,
        ));

        return $response;
    }
}
