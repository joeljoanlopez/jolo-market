<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The plain activation token is in this response only; the SPA turns it into /activate/{token}.
 */
final class ActivationLinkJson
{
    public static function response(string $userId, string $username, #[\SensitiveParameter] string $token, \DateTimeImmutable $expiresAt, int $status = 200): JsonResponse
    {
        $response = new JsonResponse([
            'id' => $userId,
            'username' => $username,
            'activationToken' => $token,
            'activationExpiresAt' => $expiresAt->format(\DATE_ATOM),
        ], $status);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
