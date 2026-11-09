<?php

declare(strict_types=1);

namespace App\Identity\Application\Activation;

use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\Port\TotpService;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Shared\Domain\Clock;

/**
 * Sets the password and starts the TOTP enrollment in one step, since TOTP is mandatory (PD-13).
 * Calling it again (for example after a reload) sets the password again and gives a new secret.
 */
final readonly class SetInitialPasswordHandler
{
    public function __construct(
        private ActivationLinks $links,
        private UserRepository $users,
        private PasswordHasher $hasher,
        private TotpService $totp,
        private SecretEncrypter $encrypter,
        private Clock $clock,
    ) {
    }

    public function __invoke(SetInitialPasswordCommand $command): TotpEnrollment
    {
        $user = $this->links->userFor($command->token);
        $password = PlainPassword::fromString($command->password);
        $now = $this->clock->now();
        $secret = $this->totp->generateSecret();

        $user->setInitialPassword($command->token, $this->hasher->hash($password), $now);
        $user->startTotpEnrollment($command->token, $this->encrypter->encrypt($secret), $now);
        $this->users->save($user);

        return new TotpEnrollment($secret, $this->totp->provisioningUri($user->username(), $secret));
    }
}
