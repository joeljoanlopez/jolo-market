<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Remembers, between both login steps, which user gave the right password.
 * It lasts five minutes: enough to read a code, too short to come back later.
 */
final class PendingLoginSession
{
    private const string KEY = 'identity.pending_login';
    private const int LIFETIME_SECONDS = 300;

    public static function start(Request $request, string $userId, int $now): void
    {
        $session = $request->getSession();
        // A new session id after the password step prevents session fixation.
        $session->migrate(true);
        $session->set(self::KEY, ['userId' => $userId, 'at' => $now]);
    }

    public static function userId(Request $request, int $now): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $pending = $request->getSession()->get(self::KEY);
        if (!\is_array($pending) || !\is_string($pending['userId'] ?? null) || !\is_int($pending['at'] ?? null)) {
            return null;
        }

        return $now - $pending['at'] < self::LIFETIME_SECONDS ? $pending['userId'] : null;
    }

    public static function clear(Request $request): void
    {
        if ($request->hasSession()) {
            $request->getSession()->remove(self::KEY);
        }
    }
}
