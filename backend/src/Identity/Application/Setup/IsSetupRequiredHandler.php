<?php

declare(strict_types=1);

namespace App\Identity\Application\Setup;

use App\Identity\Domain\Port\UserRepository;

final readonly class IsSetupRequiredHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(): bool
    {
        return !$this->users->hasAnyUser();
    }
}
