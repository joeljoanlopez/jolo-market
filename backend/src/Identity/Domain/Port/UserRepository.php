<?php

declare(strict_types=1);

namespace App\Identity\Domain\Port;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\ValueObject\UserId;

interface UserRepository
{
    /**
     * Inserts the user or updates it when it already exists.
     */
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByUsername(Username $username): ?User;

    /**
     * @param list<UserId> $ids
     *
     * @return list<User> the users found, in no particular order; unknown ids are skipped
     */
    public function findByIds(array $ids): array;

    /**
     * @return list<User> every user, ordered by username
     */
    public function findAll(): array;

    /**
     * Finds the user whose current activation link has this hash (see ActivationToken::hashOf()).
     */
    public function findByActivationTokenHash(string $hash): ?User;

    /**
     * Whether any user exists at all; first-admin setup is possible only while none does (PD-14).
     */
    public function hasAnyUser(): bool;
}
