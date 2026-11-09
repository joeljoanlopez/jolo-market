<?php

declare(strict_types=1);

namespace App\Identity\Application\Profile;

use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\PlainPassword;

final readonly class ChangePasswordHandler
{
    public function __construct(
        private ProfileUsers $profileUsers,
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {
    }

    public function __invoke(ChangePasswordCommand $command): ChangedCredentials
    {
        $user = $this->profileUsers->actor($command->actorId);
        $this->profileUsers->assertCredentials($user, $command->currentPassword, $command->code);
        $newPassword = PlainPassword::fromString($command->newPassword);

        $user->changePassword($this->hasher->hash($newPassword));
        $this->users->save($user);

        return new ChangedCredentials($user->sessionVersion());
    }
}
