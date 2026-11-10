<?php

declare(strict_types=1);

namespace App\Identity\Application\Profile;

use App\Identity\Application\Activation\TotpCodeRejected;
use App\Identity\Domain\Model\UserStateConflict;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\TotpCode;
use App\Shared\Domain\Clock;

final readonly class ConfirmTotpReEnrollmentHandler
{
    public function __construct(
        private ProfileUsers $profileUsers,
        private UserRepository $users,
        private Clock $clock,
    ) {
    }

    public function __invoke(ConfirmTotpReEnrollmentCommand $command): ChangedCredentials
    {
        $user = $this->profileUsers->actor($command->actorId);
        $code = TotpCode::fromString($command->code);
        $pendingSecret = $user->pendingTotpSecret() ?? throw UserStateConflict::noTotpReEnrollment();
        if (!$this->profileUsers->claimCode($user, $pendingSecret, $code)) {
            throw TotpCodeRejected::create();
        }

        $user->confirmTotpReEnrollment($this->clock->now());
        $this->users->save($user);

        return new ChangedCredentials($user->sessionVersion());
    }
}
