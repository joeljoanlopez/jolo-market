<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Port\SetupCodeRepository;
use App\Identity\Domain\ValueObject\SetupCode;

final class InMemorySetupCodeRepository implements SetupCodeRepository
{
    private ?SetupCode $code = null;

    public function replace(SetupCode $code): void
    {
        $this->code = $code;
    }

    public function find(): ?SetupCode
    {
        return $this->code;
    }

    public function delete(): void
    {
        $this->code = null;
    }
}
