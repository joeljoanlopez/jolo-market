<?php

declare(strict_types=1);

namespace App\Identity\Application\Activation;

use App\Identity\Domain\Model\ActivationLinkNotValid;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Shared\Domain\Clock;

/**
 * Finds the user a plain activation token belongs to, if the link is still valid.
 */
final readonly class ActivationLinks
{
    public function __construct(
        private UserRepository $users,
        private Clock $clock,
    ) {
    }

    public function userFor(#[\SensitiveParameter] string $plainToken): User
    {
        $user = $this->users->findByActivationTokenHash(ActivationToken::hashOf($plainToken))
            ?? throw ActivationLinkNotValid::create();
        $user->assertActivationLinkIsValid($plainToken, $this->clock->now());

        return $user;
    }
}
