<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Console;

use App\Catalog\Application\ReindexProductSearch\ReindexProductSearchCommand;
use App\Catalog\Application\ReindexProductSearch\ReindexProductSearchHandler;
use App\Catalog\Application\SearchProducts\SearchUnavailable;
use App\Catalog\Infrastructure\Search\InvalidSynonymFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:catalog:reindex-search',
    description: 'Rebuild the product search index from the catalog and reload the dictionary (config/search/synonyms.yaml).',
)]
final readonly class ReindexProductSearchConsoleCommand
{
    public function __construct(private ReindexProductSearchHandler $reindex)
    {
    }

    /**
     * Exit code: 0 on success, 1 when Typesense fails or the dictionary is invalid (the previous index is kept).
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Only build the index when it is missing or empty, as when the stack starts.')]
        bool $ifEmpty = false,
    ): int {
        try {
            $result = ($this->reindex)(new ReindexProductSearchCommand(onlyIfEmpty: $ifEmpty));
        } catch (SearchUnavailable|InvalidSynonymFile $failure) {
            $io->error($failure->getMessage());

            return Command::FAILURE;
        }

        if (!$result->rebuilt) {
            $io->writeln('The search index already has products; it was not rebuilt.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Indexed %d products.', $result->indexed));

        return Command::SUCCESS;
    }
}
