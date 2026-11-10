<?php

declare(strict_types=1);

namespace App\Identity\Application\Profile;

use App\Identity\Application\Activation\TotpEnrollment;
use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\Port\TotpService;
use App\Identity\Domain\Port\UserRepository;

final readonly class StartTotpReEnrollmentHandler
{
    public function __construct(
        private ProfileUsers $profileUsers,
        private UserRepository $users,
        private TotpService $totp,
        private SecretEncrypter $encrypter,
    ) {
    }

    public function __invoke(StartTotpReEnrollmentCommand $command): TotpEnrollment
    {
        $user = $this->profileUsers->actor($command->actorId);
        $this->profileUsers->assertCredentials($user, $command->currentPassword, $command->code);

        $secret = $this->totp->generateSecret();
        $user->startTotpReEnrollment($this->encrypter->encrypt($secret));
        $this->users->save($user);

        return new TotpEnrollment($secret, $this->totp->provisioningUri($user->username(), $secret));
    }
}
