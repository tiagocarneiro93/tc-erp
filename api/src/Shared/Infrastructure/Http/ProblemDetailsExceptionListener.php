<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\ProblemDetails;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Turns any exception raised by an `/api/v1` request into RFC 9457
 * `application/problem+json` (technical-scope.md §9.1), so controllers
 * never need their own try/catch-and-format boilerplate — they just let a
 * domain exception propagate. Only exceptions implementing
 * {@see ProblemDetails} (or a validation failure from `#[MapRequestPayload]`)
 * get a specific `type`; anything else still gets a problem+json envelope,
 * but with a generic type and no message that might leak internals.
 */
final class ProblemDetailsExceptionListener implements EventSubscriberInterface
{
    private const BASE_URI = 'https://tc-erp.example/problems/';

    public function __construct(
        #[Autowire(service: 'serializer.name_converter.camel_case_to_snake_case')]
        private readonly NameConverterInterface $nameConverter,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/v1')) {
            return;
        }

        $exception = $this->unwrap($event->getThrowable());
        $event->setResponse($this->toResponse($exception));
    }

    private function unwrap(\Throwable $exception): \Throwable
    {
        if ($exception instanceof HandlerFailedException) {
            // Keyed by handler class name, not a numeric index (see
            // HandlerFailedException's own docblock) — take the first value.
            foreach ($exception->getWrappedExceptions() as $wrapped) {
                return $this->unwrap($wrapped);
            }
        }

        return $exception;
    }

    private function toResponse(\Throwable $exception): JsonResponse
    {
        if ($exception instanceof ProblemDetails) {
            return $this->problem($exception->problemType(), $exception->getMessage(), $exception->httpStatus());
        }

        $validationFailure = $this->findValidationFailure($exception);

        if (null !== $validationFailure) {
            $errors = [];
            foreach ($validationFailure->getViolations() as $violation) {
                // The violation's property path is the PHP property name
                // (e.g. legalName); convert it the same way the request body
                // itself is converted, so error paths match the JSON the
                // client actually sent (technical-scope.md §9.1: snake_case).
                $errors[] = [
                    'field' => $this->nameConverter->normalize($violation->getPropertyPath()),
                    'message' => (string) $violation->getMessage(),
                ];
            }

            return new JsonResponse(
                [
                    'type' => self::BASE_URI.'validation-failed',
                    'title' => 'The request payload is invalid.',
                    'status' => 422,
                    'errors' => $errors,
                ],
                422,
                ['Content-Type' => 'application/problem+json'],
            );
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $this->problem($this->genericTypeFor($exception->getStatusCode()), $exception->getMessage(), $exception->getStatusCode());
        }

        return $this->problem('internal-error', 'Something went wrong.', 500);
    }

    private function findValidationFailure(\Throwable $exception): ?ValidationFailedException
    {
        for ($current = $exception; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof ValidationFailedException) {
                return $current;
            }
        }

        return null;
    }

    private function genericTypeFor(int $status): string
    {
        return match ($status) {
            404 => 'not-found',
            403 => 'access-denied',
            401 => 'unauthenticated',
            default => 'request-failed',
        };
    }

    private function problem(string $type, string $title, int $status): JsonResponse
    {
        return new JsonResponse(
            ['type' => self::BASE_URI.$type, 'title' => $title, 'status' => $status],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 0]];
    }
}
