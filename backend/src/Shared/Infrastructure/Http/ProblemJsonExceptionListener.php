<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\Exception\DomainError;
use App\Shared\Domain\Exception\InvalidArgument;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Domain\Exception\Unauthorized;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Renders every error as RFC 9457 application/problem+json.
 *
 * It runs after Symfony's own exception logging (priority 0) and before its
 * HTML/JSON error renderer (priority -128), so errors are still logged.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final readonly class ProblemJsonExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $status = $this->statusFor($exception);

        $problem = [
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
        ];

        // Only domain errors carry client-safe messages; anything else could leak internals.
        if ($exception instanceof DomainError) {
            $problem['detail'] = $exception->getMessage();
        }

        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $headers['Content-Type'] = 'application/problem+json';

        $event->setResponse(new JsonResponse($problem, $status, $headers));
    }

    private function statusFor(\Throwable $exception): int
    {
        return match (true) {
            $exception instanceof Unauthorized => Response::HTTP_UNAUTHORIZED,
            $exception instanceof NotFound => Response::HTTP_NOT_FOUND,
            $exception instanceof InvalidArgument => Response::HTTP_UNPROCESSABLE_ENTITY,
            $exception instanceof Conflict => Response::HTTP_CONFLICT,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
