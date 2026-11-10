<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Port\ActivationTokenGenerator;

/**
 * Predictable tokens: activation-token-0000000000000000000001, ...002, and so on.
 */
final class SequentialActivationTokenGenerator implements ActivationTokenGenerator
{
    private int $next = 1;

    public function generate(): string
    {
        return \sprintf('activation-token-%022d', $this->next++);
    }

    public static function nth(int $n): string
    {
        return \sprintf('activation-token-%022d', $n);
    }
}
