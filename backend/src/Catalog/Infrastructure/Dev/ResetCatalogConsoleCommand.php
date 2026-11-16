<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Dev;

use App\Shared\Infrastructure\Dev\ResetConfirmation;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * PD-57: empties only the tables of this context. app:dev:seed --reset runs it by name.
 */
#[AsCommand(
    name: 'app:dev:catalog:reset',
    description: 'Development only: delete every product, price snapshot and ingestion run.',
)]
final readonly class ResetCatalogConsoleCommand
{
    /** In this order, children first. */
    private const array TABLES = ['price_snapshot', 'product', 'ingestion_run'];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * Exit code: 0 once emptied, 1 when it was not confirmed.
     */
    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Option(description: 'Do not ask for confirmation.')]
        bool $force = false,
    ): int {
        if (!ResetConfirmation::granted($input, $io, $force, 'every product, price snapshot and ingestion run')) {
            return Command::FAILURE;
        }

        $this->connection->transactional(static function (Connection $connection): void {
            foreach (self::TABLES as $table) {
                $connection->executeStatement('DELETE FROM '.$table);
            }
        });
        $io->success('Deleted every product, price snapshot and ingestion run.');

        return Command::SUCCESS;
    }
}
