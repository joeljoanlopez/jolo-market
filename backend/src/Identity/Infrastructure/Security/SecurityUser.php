<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Login\SessionUser;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user Symfony keeps in the session. It holds no credentials.
 *
 * On every request Symfony reloads it through SessionUserProvider and compares both with
 * isEqualTo(): once the stored session version moves on, the session stops working.
 */
final readonly class SecurityUser implements UserInterface, EquatableInterface
{
    public function __construct(
        public string $id,
        public string $username,
        public string $role,
        public int $sessionVersion,
    ) {
    }

    public static function of(SessionUser $user): self
    {
        return new self($user->id, $user->username, $user->role, $user->sessionVersion);
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return '' !== $this->id ? $this->id : throw new \LogicException('A session user has an id.');
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return 'ADMIN' === $this->role ? ['ROLE_USER', 'ROLE_ADMIN'] : ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $user->id === $this->id && $user->sessionVersion === $this->sessionVersion;
    }
}
