<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Console;

use App\Catalog\Application\FindUnmappedCategories\CategoryCount;
use App\Catalog\Application\FindUnmappedCategories\FindUnmappedCategoriesHandler;
use App\Catalog\Application\FindUnmappedCategories\FindUnmappedCategoriesQuery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows which stored categories still land in "other", to extend the section mapping in a pull
 * request (PD-95). Read only.
 */
#[AsCommand(
    name: 'app:catalog:unmapped-categories',
    description: 'List, per supermarket, the stored categories that map to the "other" section, with their number of products.',
)]
final readonly class UnmappedCategoriesConsoleCommand
{
    public function __construct(private FindUnmappedCategoriesHandler $findUnmapped)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $unmapped = ($this->findUnmapped)(new FindUnmappedCategoriesQuery());
        if ([] === $unmapped) {
            $io->success('Every stored category maps to a section.');

            return Command::SUCCESS;
        }

        $bySupermarket = [];
        foreach ($unmapped as $category) {
            $bySupermarket[$category->supermarketId][] = $category;
        }
        foreach ($bySupermarket as $supermarketId => $categories) {
            $io->section($supermarketId);
            $io->table(
                ['Products', 'Category'],
                array_map(static fn (CategoryCount $category): array => [$category->productCount, $category->categoryPath], $categories),
            );
        }

        return Command::SUCCESS;
    }
}
