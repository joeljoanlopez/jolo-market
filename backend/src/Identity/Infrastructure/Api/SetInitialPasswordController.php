<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use App\Identity\Application\Activation\SetInitialPasswordCommand;
use App\Identity\Application\Activation\SetInitialPasswordHandler;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SetInitialPasswordController
{
    public function __construct(private SetInitialPasswordHandler $setPassword)
    {
    }

    #[Route('/api/auth/activation/{token}/password', name: 'api_auth_activation_password', methods: ['POST'])]
    public function __invoke(string $token, Request $request): JsonResponse
    {
        $enrollment = ($this->setPassword)(new SetInitialPasswordCommand($token, JsonBody::of($request)->string('password')));

        return TotpEnrollmentJson::response($enrollment);
    }
}
