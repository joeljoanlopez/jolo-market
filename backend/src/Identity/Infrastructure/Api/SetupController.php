<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use App\Identity\Application\Setup\CompleteSetupCommand;
use App\Identity\Application\Setup\CompleteSetupHandler;
use App\Identity\Application\Setup\IsSetupRequiredHandler;
use App\Identity\Application\Setup\SetupNotAvailable;
use App\Identity\Infrastructure\Api\Admin\ActivationLinkJson;
use App\Identity\Infrastructure\Http\LoginThrottle;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * First-admin setup (E3.22, PD-14). Both routes answer 404 for good once any user exists.
 */
#[AsController]
final readonly class SetupController
{
    public function __construct(
        private IsSetupRequiredHandler $isSetupRequired,
        private CompleteSetupHandler $completeSetup,
        private LoginThrottle $throttle,
    ) {
    }

    #[Route('/api/setup', name: 'api_setup_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        if (!($this->isSetupRequired)()) {
            throw SetupNotAvailable::create();
        }

        return new JsonResponse(['required' => true]);
    }

    #[Route('/api/setup', name: 'api_setup_complete', methods: ['POST'])]
    public function complete(Request $request): JsonResponse
    {
        if (!($this->isSetupRequired)()) {
            throw SetupNotAvailable::create();
        }
        $body = JsonBody::of($request);
        $command = new CompleteSetupCommand($body->string('code'), $body->string('username'));

        $this->throttle->setupAttempt($request);
        $created = ($this->completeSetup)($command);

        return ActivationLinkJson::response($created->userId, $created->username, $created->activationToken, $created->activationExpiresAt, Response::HTTP_CREATED);
    }
}
