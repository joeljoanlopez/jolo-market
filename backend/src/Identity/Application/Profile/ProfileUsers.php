<?php

declare(strict_types=1);

namespace App\Identity\Application\Profile;

use App\Identity\Application\ManageUsers\UserNotFound;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\Port\TotpService;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\EncryptedTotpSecret;
use App\Identity\Domain\ValueObject\TotpCode;
use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Loads the logged-in user and re-checks their credentials before a sensitive change (PD-38).
 */
final readonly class ProfileUsers
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private TotpService $totp,
        private SecretEncrypter $encrypter,
        private Clock $clock,
    ) {
    }

    public function actor(string $actorId): User
    {
        try {
            $id = UserId::fromString($actorId);
        } catch (InvalidUuid) {
            throw UserNotFound::withId($actorId);
        }

        return $this->users->findById($id) ?? throw UserNotFound::withId($actorId);
    }

    /**
     * A wrong password and a wrong code get the same error, so neither is confirmed on its own.
     */
    public function assertCredentials(User $user, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $code): void
    {
        $passwordHash = $user->passwordHash();
        $secret = $user->totpSecret();
        if (null === $passwordHash
            || null === $secret
            || !$this->hasher->verify($passwordHash, $password)
            || !$this->codeMatches($secret, TotpCode::fromString($code))
        ) {
            throw CredentialsRejected::create();
        }
    }

    public function codeMatches(EncryptedTotpSecret $secret, TotpCode $code): bool
    {
        return $this->totp->verify($this->encrypter->decrypt($secret), $code, $this->clock->now());
    }
}
