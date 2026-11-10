<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CSRF protection (E3.18): every state-changing /api request must carry
 * "X-Requested-With: XMLHttpRequest". A page on another site cannot add a custom header
 * without a CORS preflight, which this API never allows, and the session cookie is
 * SameSite=Lax on top of that.
 *
 * It runs after routing (priority 32), so unknown routes and methods keep their 404/405,
 * and before the firewall (priority 8).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final readonly class RequireCustomHeaderListener
{
    public const string HEADER = 'X-Requested-With';
    public const string VALUE = 'XMLHttpRequest';

    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()
            || !str_starts_with($request->getPathInfo(), '/api/')
            || \in_array($request->getMethod(), self::SAFE_METHODS, true)
        ) {
            return;
        }

        if (self::VALUE !== $request->headers->get(self::HEADER)) {
            throw new AccessDeniedHttpException(\sprintf('State-changing requests need the "%s: %s" header.', self::HEADER, self::VALUE));
        }
    }
}
