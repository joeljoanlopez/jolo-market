<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use App\Identity\Application\Login\LogInCommand;
use App\Identity\Application\Login\LogInHandler;
use App\Identity\Infrastructure\Http\LoginThrottle;
use App\Identity\Infrastructure\Http\PendingLoginSession;
use App\Shared\Domain\Clock;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * First login step. On success the session waits for the TOTP code (POST /api/auth/2fa).
 */
#[AsController]
final readonly class LogInController
{
    public function __construct(
        private LogInHandler $logIn,
        private LoginThrottle $throttle,
        private Clock $clock,
    ) {
    }

    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $body = JsonBody::of($request);
        $username = $body->string('username');
        $password = $body->string('password');

        $attempts = $this->throttle->passwordAttempt($request, $username);
        $pending = ($this->logIn)(new LogInCommand($username, $password));
        $attempts->reset();

        PendingLoginSession::start($request, $pending->userId, $this->clock->now()->getTimestamp());

        return new JsonResponse(['twoFactorRequired' => true], Response::HTTP_ACCEPTED);
    }
}
