<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Profile;

use App\Identity\Application\Profile\StartTotpReEnrollmentCommand;
use App\Identity\Application\Profile\StartTotpReEnrollmentHandler;
use App\Identity\Infrastructure\Api\TotpEnrollmentJson;
use App\Identity\Infrastructure\Http\LoginThrottle;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class StartTotpReEnrollmentController
{
    public function __construct(
        private StartTotpReEnrollmentHandler $start,
        private LoginThrottle $throttle,
    ) {
    }

    #[Route('/api/me/totp', name: 'api_me_totp', methods: ['POST'])]
    public function __invoke(#[CurrentUser] SecurityUser $actor, Request $request): JsonResponse
    {
        $body = JsonBody::of($request);
        $command = new StartTotpReEnrollmentCommand($actor->id, $body->string('currentPassword'), $body->string('code'));

        $attempts = $this->throttle->profileAttempt($request, $actor->id);
        $enrollment = ($this->start)($command);
        $attempts->reset();

        return TotpEnrollmentJson::response($enrollment);
    }
}
