<?php

declare(strict_types=1);

namespace App\Identity\Application\Setup;

use App\Identity\Application\CreateUser\CreatedUser;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\ActivationTokenGenerator;
use App\Identity\Domain\Port\SetupCodeRepository;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\UserId;

/**
 * PD-14: the setup code from the logs plus a username create the first ADMIN, pending
 * activation. The code is used up, and setup closes for good once the user exists.
 */
final readonly class CompleteSetupHandler
{
    public function __construct(
        private UserRepository $users,
        private SetupCodeRepository $codes,
        private ActivationTokenGenerator $tokens,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(CompleteSetupCommand $command): CreatedUser
    {
        if ($this->users->hasAnyUser()) {
            throw SetupNotAvailable::create();
        }
        $code = $this->codes->find();
        if (null === $code || !$code->matches($command->code)) {
            throw SetupCodeRejected::create();
        }
        $username = Username::fromString($command->username);

        $now = $this->clock->now();
        $plainToken = $this->tokens->generate();
        $activation = ActivationToken::issue($plainToken, $now);
        $admin = User::createPending(UserId::fromString($this->ids->generate()->value()), $username, Role::ADMIN, $activation, $now, null);
        $this->users->save($admin);
        $this->codes->delete();

        return new CreatedUser($admin->id()->value(), $username->value(), $plainToken, $activation->expiresAt());
    }
}
