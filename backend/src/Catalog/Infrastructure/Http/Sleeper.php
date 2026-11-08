<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

/**
 * Pauses the process. Injected so tests never really sleep.
 */
interface Sleeper
{
    public function sleep(int $milliseconds): void;
}
