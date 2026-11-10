<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\ValueObject\UserId;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public function save(User $user): void
    {
        // Stored as a copy, like a database row: later changes count only once saved again.
        $this->users[$user->id()->value()] = clone $user;
    }

    public function findById(UserId $id): ?User
    {
        $user = $this->users[$id->value()] ?? null;

        return null === $user ? null : clone $user;
    }

    public function findByUsername(Username $username): ?User
    {
        foreach ($this->users as $user) {
            if ($user->username()->equals($username)) {
                return clone $user;
            }
        }

        return null;
    }

    public function findByIds(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            $user = $this->findById($id);
            if (null !== $user) {
                $found[] = $user;
            }
        }

        return $found;
    }

    public function findAll(): array
    {
        $users = array_map(static fn (User $user): User => clone $user, array_values($this->users));
        usort($users, static fn (User $a, User $b): int => $a->username()->value() <=> $b->username()->value());

        return $users;
    }

    public function findByActivationTokenHash(string $hash): ?User
    {
        foreach ($this->users as $user) {
            if ($user->activation()?->hash() === $hash) {
                return clone $user;
            }
        }

        return null;
    }

    public function countActiveAdmins(): int
    {
        return \count(array_filter($this->users, static fn (User $user): bool => $user->isActive() && $user->isAdmin()));
    }

    public function hasAnyUser(): bool
    {
        return [] !== $this->users;
    }
}
