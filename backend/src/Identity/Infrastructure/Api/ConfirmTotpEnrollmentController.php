<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use App\Identity\Application\Activation\ConfirmTotpEnrollmentCommand;
use App\Identity\Application\Activation\ConfirmTotpEnrollmentHandler;
use App\Identity\Application\Login\FindSessionUserHandler;
use App\Identity\Application\Login\FindSessionUserQuery;
use App\Identity\Infrastructure\Http\LoginThrottle;
use App\Identity\Infrastructure\Http\SessionLogin;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Last activation step: the user is now active and logged in.
 */
#[AsController]
final readonly class ConfirmTotpEnrollmentController
{
    public function __construct(
        private ConfirmTotpEnrollmentHandler $confirm,
        private FindSessionUserHandler $findSessionUser,
        private LoginThrottle $throttle,
        private SessionLogin $sessionLogin,
    ) {
    }

    #[Route('/api/auth/activation/totp', name: 'api_auth_activation_totp', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $body = JsonBody::of($request);
        $token = $body->string('token');
        $code = $body->string('code');
        $this->throttle->activationCodeAttempt($request);

        $activated = ($this->confirm)(new ConfirmTotpEnrollmentCommand($token, $code));
        $user = ($this->findSessionUser)(new FindSessionUserQuery($activated->userId))
            ?? throw new \LogicException('An activated user is active.');

        return $this->sessionLogin->logIn($user);
    }
}
