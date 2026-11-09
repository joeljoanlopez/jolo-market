<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Console;

use App\Catalog\Application\IngestCatalog\IngestCatalogCommand;
use App\Catalog\Application\IngestCatalog\IngestCatalogHandler;
use App\Catalog\Application\IngestCatalog\IngestionRunSummary;
use App\Shared\Domain\Exception\InvalidArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:catalog:ingest',
    description: 'Import the catalog of every enabled supermarket (ENABLED_SUPERMARKETS) for INSTANCE_POSTAL_CODE.',
)]
final readonly class IngestCatalogConsoleCommand
{
    public function __construct(private IngestCatalogHandler $ingestCatalog)
    {
    }

    /**
     * Exit code: 0 when every run succeeded, 1 when any run failed, 2 for invalid input or configuration.
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Slug of the only supermarket to ingest, for example "mercadona".')]
        ?string $supermarket = null,
    ): int {
        try {
            $result = ($this->ingestCatalog)(new IngestCatalogCommand($supermarket));
        } catch (InvalidArgument $invalid) {
            $io->error($invalid->getMessage());

            return Command::INVALID;
        }

        $io->table(
            ['Supermarket', 'Status', 'Seen', 'Skipped', 'Unavailable', 'Duration (s)', 'Error'],
            array_map(self::row(...), $result->runs),
        );

        return $result->allSucceeded() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return list<string|int>
     */
    private static function row(IngestionRunSummary $run): array
    {
        $duration = null === $run->finishedAt ? '' : (string) ($run->finishedAt->getTimestamp() - $run->startedAt->getTimestamp());

        return [$run->supermarket, $run->status, $run->productsSeen, $run->productsSkipped, $run->productsMarkedUnavailable, $duration, $run->error ?? ''];
    }
}
