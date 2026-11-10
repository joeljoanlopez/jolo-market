<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Port\SetupCodeGenerator;

/**
 * Predictable codes: AAAA-AAAA-AAAA-AAA1, ...AAA2, and so on.
 */
final class SequentialSetupCodeGenerator implements SetupCodeGenerator
{
    private int $next = 1;

    public function generate(): string
    {
        return self::nth($this->next++);
    }

    public static function nth(int $n): string
    {
        return 'AAAA-AAAA-AAAA-AAA'.$n;
    }
}
