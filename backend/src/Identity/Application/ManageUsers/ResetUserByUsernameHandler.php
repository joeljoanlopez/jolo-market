<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

use App\Identity\Domain\Port\ActivationTokenGenerator;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\InvalidUsername;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Clock;

/**
 * The operator's way out when the only admin is locked out (PD-35). Only reachable from the
 * server console, so it has no actor check.
 */
final readonly class ResetUserByUsernameHandler
{
    public function __construct(
        private UserRepository $users,
        private ActivationTokenGenerator $tokens,
        private Clock $clock,
    ) {
    }

    public function __invoke(ResetUserByUsernameCommand $command): NewActivationLink
    {
        try {
            $username = Username::fromString($command->username);
        } catch (InvalidUsername) {
            throw UserNotFound::named($command->username);
        }
        $user = $this->users->findByUsername($username) ?? throw UserNotFound::named($command->username);

        $plainToken = $this->tokens->generate();
        $activation = ActivationToken::issue($plainToken, $this->clock->now());
        $user->reset($activation);
        $this->users->save($user);

        return new NewActivationLink($user->id()->value(), $user->username()->value(), $plainToken, $activation->expiresAt());
    }
}
