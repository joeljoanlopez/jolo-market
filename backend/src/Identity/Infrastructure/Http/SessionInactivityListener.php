<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies SessionInactivity before the firewall (priority 8) reads the session, so an expired
 * session is already empty when Symfony looks for a logged-in user.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 12)]
final readonly class SessionInactivityListener
{
    private const string LAST_ACTIVITY = 'identity.last_activity_at';
    private const string COOKIE_RENEWED = 'identity.cookie_renewed_at';

    public function __construct(private SessionInactivity $inactivity)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasPreviousSession()) {
            return;
        }

        $session = $request->getSession();
        $lastActivityAt = $session->get(self::LAST_ACTIVITY);
        if ($this->inactivity->hasExpired(\is_int($lastActivityAt) ? $lastActivityAt : null)) {
            $session->invalidate();

            return;
        }

        $renewedAt = $session->get(self::COOKIE_RENEWED);
        if ($this->inactivity->cookieNeedsRenewal(\is_int($renewedAt) ? $renewedAt : null)) {
            // A new session id comes with a new cookie, valid for cookie_lifetime (7 days).
            $session->migrate(false, SessionInactivity::LIFETIME_SECONDS);
            $session->set(self::COOKIE_RENEWED, $this->inactivity->now());
        }
        $session->set(self::LAST_ACTIVITY, $this->inactivity->now());
    }
}
