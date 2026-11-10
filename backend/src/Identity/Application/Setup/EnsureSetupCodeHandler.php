<?php

declare(strict_types=1);

namespace App\Identity\Application\Setup;

use App\Identity\Domain\Port\SetupCodeGenerator;
use App\Identity\Domain\Port\SetupCodeRepository;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\SetupCode;
use App\Shared\Domain\Clock;

/**
 * Runs at every container start (PD-14). While there are no users it replaces the setup code
 * and returns the plain one, for the logs; afterwards it returns null and removes any old code.
 */
final readonly class EnsureSetupCodeHandler
{
    public function __construct(
        private UserRepository $users,
        private SetupCodeRepository $codes,
        private SetupCodeGenerator $generator,
        private Clock $clock,
    ) {
    }

    public function __invoke(): ?string
    {
        if ($this->users->hasAnyUser()) {
            $this->codes->delete();

            return null;
        }

        $plainCode = $this->generator->generate();
        $this->codes->replace(SetupCode::issue($plainCode, $this->clock->now()));

        return $plainCode;
    }
}
