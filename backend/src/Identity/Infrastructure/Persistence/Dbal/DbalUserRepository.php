<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Dbal;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\EncryptedTotpSecret;
use App\Identity\Domain\ValueObject\PasswordHash;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Shared\Infrastructure\Persistence\Dbal\DbalDateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalUserRepository implements UserRepository
{
    private const string COLUMNS = 'id, username, role, status, password_hash, totp_secret_encrypted, totp_confirmed_at,
        pending_totp_secret_encrypted, activation_token_hash, activation_expires_at, session_version, created_at, created_by';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * Inserts or updates by id. No ON DUPLICATE KEY UPDATE here: it would also fire on the unique
     * username and overwrite another user instead of failing.
     */
    public function save(User $user): void
    {
        $activation = $user->activation();
        $totpConfirmedAt = $user->totpConfirmedAt();
        $id = BinaryUuid::toDatabase($user->id()->value());
        $mutable = [
            'role' => $user->role()->value,
            'status' => $user->status()->value,
            'password_hash' => $user->passwordHash()?->value(),
            'totp_secret_encrypted' => $user->totpSecret()?->value(),
            'totp_confirmed_at' => null === $totpConfirmedAt ? null : DbalDateTime::toDatabase($totpConfirmedAt),
            'pending_totp_secret_encrypted' => $user->pendingTotpSecret()?->value(),
            'activation_token_hash' => $activation?->hash(),
            'activation_expires_at' => null === $activation ? null : DbalDateTime::toDatabase($activation->expiresAt()),
            'session_version' => $user->sessionVersion(),
        ];

        $exists = false !== $this->connection->fetchOne('SELECT 1 FROM user WHERE id = ?', [$id], [ParameterType::BINARY]);
        if ($exists) {
            $this->connection->update('user', $mutable, ['id' => $id], ['id' => ParameterType::BINARY, 'session_version' => ParameterType::INTEGER]);

            return;
        }

        $createdBy = $user->createdBy();
        $this->connection->insert(
            'user',
            [
                'id' => $id,
                'username' => $user->username()->value(),
                ...$mutable,
                'created_at' => DbalDateTime::toDatabase($user->createdAt()),
                'created_by' => null === $createdBy ? null : BinaryUuid::toDatabase($createdBy->value()),
            ],
            ['id' => ParameterType::BINARY, 'created_by' => ParameterType::BINARY, 'session_version' => ParameterType::INTEGER],
        );
    }

    public function findById(UserId $id): ?User
    {
        return $this->findOneWhere('id = ?', BinaryUuid::toDatabase($id->value()), ParameterType::BINARY);
    }

    public function findByUsername(Username $username): ?User
    {
        return $this->findOneWhere('username = ?', $username->value(), ParameterType::STRING);
    }

    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM user WHERE id IN (?)',
            [array_map(static fn (UserId $id): string => BinaryUuid::toDatabase($id->value()), $ids)],
            [ArrayParameterType::BINARY],
        );

        return array_map(self::hydrate(...), $rows);
    }

    public function findAll(): array
    {
        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative('SELECT '.self::COLUMNS.' FROM user ORDER BY username'));
    }

    public function findByActivationTokenHash(string $hash): ?User
    {
        return $this->findOneWhere('activation_token_hash = ?', $hash, ParameterType::STRING);
    }

    public function countActiveAdmins(): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user WHERE role = ? AND status = ?',
            [Role::ADMIN->value, UserStatus::ACTIVE->value],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    public function hasAnyUser(): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM user LIMIT 1');
    }

    private function findOneWhere(string $condition, string $value, ParameterType $type): ?User
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM user WHERE '.$condition, [$value], [$type]);

        return false === $row ? null : self::hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): User
    {
        $activationHash = self::nullableString($row['activation_token_hash']);
        $activationExpiresAt = self::nullableString($row['activation_expires_at']);
        $totpSecret = self::nullableString($row['totp_secret_encrypted']);
        $totpConfirmedAt = self::nullableString($row['totp_confirmed_at']);
        $pendingSecret = self::nullableString($row['pending_totp_secret_encrypted']);
        $passwordHash = self::nullableString($row['password_hash']);
        $createdBy = self::nullableString($row['created_by']);

        return User::reconstitute(
            UserId::fromString(BinaryUuid::fromDatabase(self::string($row['id']))),
            Username::fromString(self::string($row['username'])),
            Role::fromName(self::string($row['role'])),
            UserStatus::from(self::string($row['status'])),
            null === $passwordHash ? null : PasswordHash::fromString($passwordHash),
            null === $totpSecret ? null : EncryptedTotpSecret::fromString($totpSecret),
            null === $totpConfirmedAt ? null : DbalDateTime::fromDatabase($totpConfirmedAt),
            null === $pendingSecret ? null : EncryptedTotpSecret::fromString($pendingSecret),
            null === $activationHash || null === $activationExpiresAt
                ? null
                : ActivationToken::reconstitute($activationHash, DbalDateTime::fromDatabase($activationExpiresAt)),
            DbalDateTime::fromDatabase(self::string($row['created_at'])),
            null === $createdBy ? null : UserId::fromString(BinaryUuid::fromDatabase($createdBy)),
            is_numeric($row['session_version']) ? (int) $row['session_version'] : throw new \UnexpectedValueException('session_version is not a number.'),
        );
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \UnexpectedValueException('Expected a string column.');
    }

    private static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }
}
