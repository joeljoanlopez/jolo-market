<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\EncryptedTotpSecret;
use App\Identity\Domain\ValueObject\PasswordHash;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Builds users in a given state through the real aggregate methods.
 * Passwords are stored as FakePasswordHasher and FakeSecretEncrypter would store them.
 */
final class Users
{
    public const string CREATED_AT = '2026-11-09 10:00:00';
    public const string PASSWORD = 'Correct-horse-1';
    public const string TOTP_SECRET = 'ACTIVESECRET';

    public static function pending(string $id, string $username, string $plainToken, Role $role = Role::USER, string $createdAt = self::CREATED_AT): User
    {
        return User::createPending(
            UserId::fromString($id),
            Username::fromString($username),
            $role,
            ActivationToken::issue($plainToken, self::at($createdAt)),
            self::at($createdAt),
            null,
        );
    }

    public static function active(string $id, string $username, Role $role = Role::USER): User
    {
        $token = \sprintf('setup-token-for-%s-%016d', $username, 0);
        $user = self::pending($id, $username, $token, $role);
        $now = self::at(self::CREATED_AT);
        $user->setInitialPassword($token, PasswordHash::fromString('hashed:'.self::PASSWORD), $now);
        $user->startTotpEnrollment($token, EncryptedTotpSecret::fromString('encrypted:'.self::TOTP_SECRET), $now);
        $user->confirmTotp($token, $now);

        return $user;
    }

    public static function at(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC'));
    }
}
