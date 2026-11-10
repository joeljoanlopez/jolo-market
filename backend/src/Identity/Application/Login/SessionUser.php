<?php

declare(strict_types=1);

namespace App\Identity\Application\Login;

use App\Identity\Domain\Model\User;

/**
 * What a logged-in session knows about its user. A session stops working once the user's
 * session version moves on (reset, disabled, new password, new TOTP device).
 */
final readonly class SessionUser
{
    public function __construct(
        public string $id,
        public string $username,
        public string $role,
        public int $sessionVersion,
    ) {
    }

    public static function of(User $user): self
    {
        return new self($user->id()->value(), $user->username()->value(), $user->role()->value, $user->sessionVersion());
    }
}
