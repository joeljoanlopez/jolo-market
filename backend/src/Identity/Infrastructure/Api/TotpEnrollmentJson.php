<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use App\Identity\Application\Activation\TotpEnrollment;
use Symfony\Component\HttpFoundation\JsonResponse;

final class TotpEnrollmentJson
{
    public static function response(TotpEnrollment $enrollment): JsonResponse
    {
        $response = new JsonResponse(['secret' => $enrollment->secret, 'provisioningUri' => $enrollment->provisioningUri]);
        // The secret must not stay in any cache.
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
