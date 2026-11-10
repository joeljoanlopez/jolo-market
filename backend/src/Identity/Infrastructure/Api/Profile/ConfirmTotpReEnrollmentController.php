<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Profile;

use App\Identity\Application\Login\FindSessionUserHandler;
use App\Identity\Application\Profile\ConfirmTotpReEnrollmentCommand;
use App\Identity\Application\Profile\ConfirmTotpReEnrollmentHandler;
use App\Identity\Infrastructure\Http\LoginThrottle;
use App\Identity\Infrastructure\Http\SessionLogin;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * PD-38: the new device replaces the old one, every other session ends and this one stays.
 */
#[AsController]
final readonly class ConfirmTotpReEnrollmentController
{
    public function __construct(
        private ConfirmTotpReEnrollmentHandler $confirm,
        private FindSessionUserHandler $findSessionUser,
        private LoginThrottle $throttle,
        private SessionLogin $sessionLogin,
    ) {
    }

    #[Route('/api/me/totp/confirm', name: 'api_me_totp_confirm', methods: ['POST'])]
    public function __invoke(#[CurrentUser] SecurityUser $actor, Request $request): Response
    {
        $code = JsonBody::of($request)->string('code');

        $attempts = $this->throttle->profileAttempt($request, $actor->id);
        ($this->confirm)(new ConfirmTotpReEnrollmentCommand($actor->id, $code));
        $attempts->reset();

        $this->sessionLogin->renew($this->findSessionUser, $actor->id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
