<?php

declare(strict_types=1);

namespace App\Identity\Application\Login;

use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\InvalidUsername;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Identity\Domain\ValueObject\Username;

/**
 * First login step: username and password.
 */
final readonly class LogInHandler
{
    private const string DUMMY_PASSWORD = 'Dummy-password-1';

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {
    }

    public function __invoke(LogInCommand $command): PendingLogin
    {
        try {
            $user = $this->users->findByUsername(Username::fromString($command->username));
        } catch (InvalidUsername) {
            $user = null;
        }

        $passwordHash = $user?->passwordHash();
        if (null === $user || null === $passwordHash) {
            // Hash anyway, so an unknown username takes as long as a wrong password.
            $this->hasher->hash(PlainPassword::fromString(self::DUMMY_PASSWORD));

            throw InvalidCredentials::create();
        }

        if (!$this->hasher->verify($passwordHash, $command->password) || !$user->isActive()) {
            throw InvalidCredentials::create();
        }

        return new PendingLogin($user->id()->value());
    }
}
