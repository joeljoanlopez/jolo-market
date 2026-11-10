<?php

declare(strict_types=1);

namespace App\Identity\Domain\Port;

use App\Identity\Domain\ValueObject\SetupCode;

/**
 * Holds at most one setup code: the latest one generated.
 */
interface SetupCodeRepository
{
    public function replace(SetupCode $code): void;

    public function find(): ?SetupCode;

    public function delete(): void;
}
