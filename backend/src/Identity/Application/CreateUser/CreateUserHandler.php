<?php

declare(strict_types=1);

namespace App\Identity\Application\CreateUser;

use App\Identity\Application\AdminActor;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\ActivationTokenGenerator;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\UserId;

final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepository $users,
        private AdminActor $adminActor,
        private ActivationTokenGenerator $tokens,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateUserCommand $command): CreatedUser
    {
        $admin = $this->adminActor->require($command->actorId);
        $username = Username::fromString($command->username);
        $role = Role::fromName($command->role);

        if (null !== $this->users->findByUsername($username)) {
            throw UsernameAlreadyTaken::named($username->value());
        }

        $now = $this->clock->now();
        $plainToken = $this->tokens->generate();
        $activation = ActivationToken::issue($plainToken, $now);
        $user = User::createPending(UserId::fromString($this->ids->generate()->value()), $username, $role, $activation, $now, $admin->id());
        $this->users->save($user);

        return new CreatedUser($user->id()->value(), $username->value(), $plainToken, $activation->expiresAt());
    }
}
