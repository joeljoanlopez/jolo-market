<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Dev;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\ActivationTokenGenerator;
use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\UserId;

/**
 * The dummy users of the development environment (PD-56). Their password and TOTP secrets are
 * public on purpose: they exist only in APP_ENV=dev.
 *
 * They are created active through the real activation steps, so they are stored exactly like
 * users who activated their link. A user that already exists, dummy or not, is never touched.
 */
final readonly class DevUsers
{
    public const string ADMIN = 'admin';
    public const string USER = 'usuario';
    public const string PASSWORD = 'Dev-password-1';

    /** Fixed base32 TOTP secrets, so an authenticator app keeps working after a reset. */
    private const array TOTP_SECRETS = [
        self::ADMIN => 'DEVADMINJOLOMARKETDEVADMINJOLOMA',
        self::USER => 'DEVUSUARIOJOLOMARKETDEVUSUARIOJO',
    ];

    private const array ROLES = [
        self::ADMIN => Role::ADMIN,
        self::USER => Role::USER,
    ];

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private SecretEncrypter $secretEncrypter,
        private ActivationTokenGenerator $tokens,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public static function totpSecretOf(string $username): string
    {
        return self::TOTP_SECRETS[$username] ?? throw new \InvalidArgumentException(\sprintf('"%s" is not a dummy user.', $username));
    }

    /**
     * @return array<string, bool> by username, whether it was created now (false: it already existed)
     */
    public function createMissing(): array
    {
        $created = [];
        foreach (self::ROLES as $name => $role) {
            $username = Username::fromString($name);
            if (null !== $this->users->findByUsername($username)) {
                $created[$name] = false;
                continue;
            }

            $this->users->save($this->activeUser($username, $role, self::TOTP_SECRETS[$name]));
            $created[$name] = true;
        }

        return $created;
    }

    private function activeUser(Username $username, Role $role, string $totpSecret): User
    {
        $now = $this->clock->now();
        $token = $this->tokens->generate();
        $user = User::createPending(UserId::fromString($this->ids->generate()->value()), $username, $role, ActivationToken::issue($token, $now), $now, null);
        $user->setInitialPassword($token, $this->passwordHasher->hash(PlainPassword::fromString(self::PASSWORD)), $now);
        $user->startTotpEnrollment($token, $this->secretEncrypter->encrypt($totpSecret), $now);
        $user->confirmTotp($token, $now);

        return $user;
    }
}
