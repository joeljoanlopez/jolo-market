<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Profile;

use App\Identity\Application\Login\FindSessionUserHandler;
use App\Identity\Application\Profile\ChangePasswordCommand;
use App\Identity\Application\Profile\ChangePasswordHandler;
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
 * PD-38: every other session ends; this one moves to the new session version.
 */
#[AsController]
final readonly class ChangePasswordController
{
    public function __construct(
        private ChangePasswordHandler $changePassword,
        private FindSessionUserHandler $findSessionUser,
        private LoginThrottle $throttle,
        private SessionLogin $sessionLogin,
    ) {
    }

    #[Route('/api/me/password', name: 'api_me_password', methods: ['POST'])]
    public function __invoke(#[CurrentUser] SecurityUser $actor, Request $request): Response
    {
        $body = JsonBody::of($request);
        $command = new ChangePasswordCommand($actor->id, $body->string('currentPassword'), $body->string('code'), $body->string('newPassword'));

        $attempts = $this->throttle->profileAttempt($request, $actor->id);
        ($this->changePassword)($command);
        $attempts->reset();

        $this->sessionLogin->renew($this->findSessionUser, $actor->id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
