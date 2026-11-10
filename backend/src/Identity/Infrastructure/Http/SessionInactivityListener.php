<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies SessionInactivity (PD-37, PD-40).
 *
 * On request it runs before the firewall (priority 8) reads the session, so an expired session
 * is already empty when Symfony looks for a logged-in user.
 *
 * Renewing the cookie keeps the session id: Symfony only re-sends the cookie when the id
 * changes, so this listener re-sends it itself, after Symfony's session listener (-1000). A new
 * id would destroy the old one, and a request the browser sent at the same moment would then
 * get a 401. The id still changes on every login, against session fixation.
 */
final readonly class SessionInactivityListener
{
    private const string LAST_ACTIVITY = 'identity.last_activity_at';
    private const string COOKIE_RENEWED = 'identity.cookie_renewed_at';
    private const string RENEW_COOKIE = '_identity_renew_session_cookie';

    /**
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(
        private SessionInactivity $inactivity,
        #[Autowire(param: 'session.storage.options')]
        private array $sessionOptions,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 12)]
    public function onRequest(RequestEvent $event): void
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
            $session->set(self::COOKIE_RENEWED, $this->inactivity->now());
            $request->attributes->set(self::RENEW_COOKIE, true);
        }
        $session->set(self::LAST_ACTIVITY, $this->inactivity->now());
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -1001)]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || true !== $request->attributes->get(self::RENEW_COOKIE) || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $response = $event->getResponse();
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $session->getName()) {
                // Symfony already sent it: the session got a new id (login) or was ended (logout).
                return;
            }
        }
        if ([] === $session->all()) {
            return;
        }

        $secure = $this->sessionOptions['cookie_secure'] ?? 'auto';
        $sameSite = $this->sessionOptions['cookie_samesite'] ?? null;
        $response->headers->setCookie(Cookie::create(
            $session->getName(),
            $session->getId(),
            $this->inactivity->now() + SessionInactivity::LIFETIME_SECONDS,
            '/',
            null,
            'auto' === $secure ? $request->isSecure() : (bool) $secure,
            (bool) ($this->sessionOptions['cookie_httponly'] ?? true),
            false,
            \in_array($sameSite, [Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE], true) ? $sameSite : Cookie::SAMESITE_LAX,
        ));
    }
}
