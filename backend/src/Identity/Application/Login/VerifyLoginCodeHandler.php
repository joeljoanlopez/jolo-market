<?php

declare(strict_types=1);

namespace App\Identity\Application\Login;

use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\Port\TotpService;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\InvalidTotpCode;
use App\Identity\Domain\ValueObject\TotpCode;
use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Second login step: the TOTP code of the user who passed the first one.
 */
final readonly class VerifyLoginCodeHandler
{
    public function __construct(
        private UserRepository $users,
        private TotpService $totp,
        private SecretEncrypter $encrypter,
        private Clock $clock,
    ) {
    }

    public function __invoke(VerifyLoginCodeCommand $command): SessionUser
    {
        try {
            $user = $this->users->findById(UserId::fromString($command->userId));
            $code = TotpCode::fromString($command->code);
        } catch (InvalidUuid|InvalidTotpCode) {
            throw InvalidCredentials::create();
        }

        $secret = $user?->totpSecret();
        if (null === $user || null === $secret || !$user->isActive()
            || !$this->totp->verify($this->encrypter->decrypt($secret), $code, $this->clock->now())
        ) {
            throw InvalidCredentials::create();
        }

        return SessionUser::of($user);
    }
}
