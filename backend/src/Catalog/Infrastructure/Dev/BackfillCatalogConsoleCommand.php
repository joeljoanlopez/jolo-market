<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Dev;

use App\Catalog\Application\IngestCatalog\IngestCatalogCommand;
use App\Catalog\Application\IngestCatalog\IngestCatalogHandler;
use App\Catalog\Application\IngestCatalog\IngestionSettings;
use App\Shared\Infrastructure\Dev\SettableClock;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * PD-55: gives a fresh development database a price history. It runs the dummy ingestion of
 * every enabled chain once per day, from N days ago to today, with the clock moved to that day,
 * so runs and snapshots carry that date. Days that already have a run of that chain are skipped.
 */
#[AsCommand(
    name: 'app:dev:catalog:backfill',
    description: 'Development only: run the dummy catalog ingestion once per day for the last N days (price history).',
)]
final readonly class BackfillCatalogConsoleCommand
{
    private const int MIN_DAYS = 1;
    private const int MAX_DAYS = 730;

    public function __construct(
        #[Autowire(service: 'catalog.dev.backfill_handler')]
        private IngestCatalogHandler $ingest,
        private IngestionSettings $settings,
        private SettableClock $clock,
        private DbalIngestionRunDays $runDays,
    ) {
    }

    /**
     * Exit code: 0 when every run succeeded, 1 when any run failed, 2 for an invalid --days.
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'How many days, today included (1 to 730).')]
        string $days = '180',
    ): int {
        if (1 !== preg_match('/^\d{1,3}$/D', $days) || (int) $days < self::MIN_DAYS || (int) $days > self::MAX_DAYS) {
            $io->error(\sprintf('--days must be a whole number from %d to %d, got "%s".', self::MIN_DAYS, self::MAX_DAYS, $days));

            return Command::INVALID;
        }

        $today = $this->clock->now();
        $firstDay = $today->setTimezone(new \DateTimeZone('UTC'))->modify(\sprintf('-%d days', (int) $days - 1))->setTime(0, 0);
        $daysWithRun = $this->runDays->since($firstDay);

        $runs = 0;
        $failed = 0;
        try {
            for ($offset = (int) $days - 1; $offset >= 0; --$offset) {
                $instant = $today->modify(\sprintf('-%d days', $offset));
                $day = $instant->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
                foreach ($this->settings->enabledSupermarkets() as $supermarket) {
                    if (\in_array($day, $daysWithRun[$supermarket->value()] ?? [], true)) {
                        continue;
                    }

                    $this->clock->set($instant);
                    $result = ($this->ingest)(new IngestCatalogCommand($supermarket->value()));
                    ++$runs;
                    $failed += $result->allSucceeded() ? 0 : 1;
                }
            }
        } finally {
            $this->clock->reset();
        }

        if ($failed > 0) {
            $io->error(\sprintf('%d of %d dummy runs failed. See the ingestion log.', $failed, $runs));

            return Command::FAILURE;
        }

        $io->success(0 === $runs
            ? \sprintf('Every one of the last %s days already has a run: nothing to do.', $days)
            : \sprintf('%d dummy runs imported over the last %s days.', $runs, $days));

        return Command::SUCCESS;
    }
}
