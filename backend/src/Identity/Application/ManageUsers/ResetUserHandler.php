<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

use App\Identity\Domain\Port\ActivationTokenGenerator;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Shared\Domain\Clock;

/**
 * PD-35. An admin uses the profile page for their own account, never a reset.
 */
final readonly class ResetUserHandler
{
    public function __construct(
        private ManagedUsers $managedUsers,
        private UserRepository $users,
        private ActivationTokenGenerator $tokens,
        private Clock $clock,
    ) {
    }

    public function __invoke(ResetUserCommand $command): NewActivationLink
    {
        $user = $this->managedUsers->targetOf($command->actorId, $command->userId);
        if ($user->id()->value() === $command->actorId) {
            throw CannotManageYourself::create();
        }

        $plainToken = $this->tokens->generate();
        $activation = ActivationToken::issue($plainToken, $this->clock->now());
        $user->reset($activation);
        $this->users->save($user);

        return new NewActivationLink($user->id()->value(), $user->username()->value(), $plainToken, $activation->expiresAt());
    }
}
