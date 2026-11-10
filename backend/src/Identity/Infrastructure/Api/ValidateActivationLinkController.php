<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use App\Identity\Application\Activation\ValidateActivationTokenHandler;
use App\Identity\Application\Activation\ValidateActivationTokenQuery;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The token travels in the body, never in the URL, so it stays out of access and router logs.
 */
#[AsController]
final readonly class ValidateActivationLinkController
{
    public function __construct(private ValidateActivationTokenHandler $validate)
    {
    }

    #[Route('/api/auth/activation', name: 'api_auth_activation', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $link = ($this->validate)(new ValidateActivationTokenQuery(JsonBody::of($request)->string('token')));

        return new JsonResponse(['username' => $link->username, 'expiresAt' => $link->expiresAt->format(\DATE_ATOM)]);
    }
}
