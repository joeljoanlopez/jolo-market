<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Console;

use App\Catalog\Application\PrunePriceHistory\PrunePriceHistoryCommand;
use App\Catalog\Application\PrunePriceHistory\PrunePriceHistoryHandler;
use App\Shared\Domain\Exception\InvalidArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:catalog:prune-history',
    description: 'Delete price snapshots older than PRICE_HISTORY_RETENTION_MONTHS, keeping the latest one of each product.',
)]
final readonly class PrunePriceHistoryConsoleCommand
{
    public function __construct(
        private PrunePriceHistoryHandler $pruneHistory,
        #[Autowire(env: 'int:PRICE_HISTORY_RETENTION_MONTHS')]
        private int $retentionMonths,
    ) {
    }

    /**
     * Exit code: 0 on success, 2 for an invalid PRICE_HISTORY_RETENTION_MONTHS (nothing is deleted).
     */
    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $result = ($this->pruneHistory)(new PrunePriceHistoryCommand($this->retentionMonths));
        } catch (InvalidArgument $invalid) {
            $io->error($invalid->getMessage());

            return Command::INVALID;
        }

        $io->success(\sprintf(
            'Deleted %d price snapshots captured before %s UTC (retention: %d months; the latest snapshot of each product is kept).',
            $result->deleted,
            $result->cutoff->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $this->retentionMonths,
        ));

        return Command::SUCCESS;
    }
}
