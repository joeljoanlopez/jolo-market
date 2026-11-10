<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use App\Identity\Application\Login\InvalidCredentials;
use App\Identity\Application\Login\VerifyLoginCodeCommand;
use App\Identity\Application\Login\VerifyLoginCodeHandler;
use App\Identity\Infrastructure\Http\LoginThrottle;
use App\Identity\Infrastructure\Http\PendingLoginSession;
use App\Identity\Infrastructure\Http\SessionLogin;
use App\Shared\Domain\Clock;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Second login step: the TOTP code. Only then is the session authenticated (PD-13).
 */
#[AsController]
final readonly class VerifyLoginCodeController
{
    public function __construct(
        private VerifyLoginCodeHandler $verifyCode,
        private LoginThrottle $throttle,
        private SessionLogin $sessionLogin,
        private Clock $clock,
    ) {
    }

    #[Route('/api/auth/2fa', name: 'api_auth_2fa', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $code = JsonBody::of($request)->string('code');
        $userId = PendingLoginSession::userId($request, $this->clock->now()->getTimestamp())
            ?? throw InvalidCredentials::create();

        $attempts = $this->throttle->codeAttempt($request, $userId);
        $user = ($this->verifyCode)(new VerifyLoginCodeCommand($userId, $code));
        $attempts->reset();
        PendingLoginSession::clear($request);

        return $this->sessionLogin->logIn($user);
    }
}
