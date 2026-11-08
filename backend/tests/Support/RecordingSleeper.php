<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Catalog\Infrastructure\Http\Sleeper;

/**
 * Records the pauses instead of sleeping, and moves the fixed clock forward by them.
 */
final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public private(set) array $pausesInMilliseconds = [];

    public function __construct(private readonly FixedClock $clock)
    {
    }

    public function sleep(int $milliseconds): void
    {
        $this->pausesInMilliseconds[] = $milliseconds;
        $this->clock->advance(new \DateInterval(\sprintf('PT%dS', intdiv($milliseconds, 1000))));
        $remainder = $milliseconds % 1000;
        if ($remainder > 0) {
            $this->clock->advanceMicroseconds($remainder * 1000);
        }
    }
}
