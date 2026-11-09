<?php

declare(strict_types=1);

namespace App\Identity\Application\Activation;

use App\Identity\Domain\Model\ActivationStepOutOfOrder;
use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\Port\TotpService;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\TotpCode;
use App\Shared\Domain\Clock;

final readonly class ConfirmTotpEnrollmentHandler
{
    public function __construct(
        private ActivationLinks $links,
        private UserRepository $users,
        private TotpService $totp,
        private SecretEncrypter $encrypter,
        private Clock $clock,
    ) {
    }

    public function __invoke(ConfirmTotpEnrollmentCommand $command): ActivatedUser
    {
        $user = $this->links->userFor($command->token);
        $code = TotpCode::fromString($command->code);
        $now = $this->clock->now();

        if (null === $user->passwordHash()) {
            throw ActivationStepOutOfOrder::passwordMissing();
        }
        $pendingSecret = $user->pendingTotpSecret() ?? throw ActivationStepOutOfOrder::totpEnrollmentMissing();
        if (!$this->totp->verify($this->encrypter->decrypt($pendingSecret), $code, $now)) {
            throw TotpCodeRejected::create();
        }

        $user->confirmTotp($command->token, $now);
        $this->users->save($user);

        return new ActivatedUser($user->id()->value());
    }
}
