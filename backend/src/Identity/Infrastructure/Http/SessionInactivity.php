<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Shared\Domain\Clock;

/**
 * PD-37: a session ends after 7 days without requests, and each request starts the 7 days
 * again. The cookie is re-sent at most once an hour so the browser keeps it for 7 more days.
 */
final readonly class SessionInactivity
{
    public const int LIFETIME_SECONDS = 604800;

    private const int COOKIE_RENEWAL_SECONDS = 3600;

    public function __construct(private Clock $clock)
    {
    }

    public function hasExpired(?int $lastActivityAt): bool
    {
        return null !== $lastActivityAt && $this->now() - $lastActivityAt >= self::LIFETIME_SECONDS;
    }

    public function cookieNeedsRenewal(?int $cookieRenewedAt): bool
    {
        return null === $cookieRenewedAt || $this->now() - $cookieRenewedAt >= self::COOKIE_RENEWAL_SECONDS;
    }

    public function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
