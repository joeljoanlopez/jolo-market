<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

final class UsleepSleeper implements Sleeper
{
    public function sleep(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
