<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
final readonly class LogOutController
{
    public function __construct(private TokenStorageInterface $tokens)
    {
    }

    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $this->tokens->setToken(null);
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
