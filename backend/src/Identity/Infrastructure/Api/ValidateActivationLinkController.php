<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use App\Identity\Application\Activation\ValidateActivationTokenHandler;
use App\Identity\Application\Activation\ValidateActivationTokenQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ValidateActivationLinkController
{
    public function __construct(private ValidateActivationTokenHandler $validate)
    {
    }

    #[Route('/api/auth/activation/{token}', name: 'api_auth_activation', methods: ['GET'])]
    public function __invoke(string $token): JsonResponse
    {
        $link = ($this->validate)(new ValidateActivationTokenQuery($token));

        return new JsonResponse(['username' => $link->username, 'expiresAt' => $link->expiresAt->format(\DATE_ATOM)]);
    }
}
