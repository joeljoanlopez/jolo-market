<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\SuggestProducts\SuggestionCandidate;
use App\Catalog\Application\SuggestProducts\SuggestionCandidates;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use Doctrine\DBAL\Connection;

/**
 * Products whose name or brand contains one of the words anywhere: a superset of the word-start
 * matches the handler scores. The accent-insensitive collation makes "platano" find "Plátano".
 * When there are too many, the ones containing more of the words are kept.
 */
final readonly class DbalSuggestionCandidates implements SuggestionCandidates
{
    public const int DEFAULT_MAX_CANDIDATES = 200;

    public function __construct(
        private Connection $connection,
        private int $maxCandidates = self::DEFAULT_MAX_CANDIDATES,
    ) {
    }

    public function matchingAnyWord(SupermarketId $supermarketId, array $words): array
    {
        $parameters = ['supermarket' => $supermarketId->value()];
        $matches = [];
        foreach ($words as $index => $word) {
            $parameters['w'.$index] = '%'.addcslashes($word, '%_\\').'%';
            $matches[] = \sprintf("(CONCAT(p.name, ' ', COALESCE(p.brand, '')) LIKE :w%d)", $index);
        }

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT p.id, p.name, p.brand, p.available, (%s) AS matches
                 FROM product p
                 WHERE p.supermarket_id = :supermarket AND (%s)
                 ORDER BY matches DESC, p.name, p.id
                 LIMIT %d',
                implode(' + ', $matches),
                implode(' OR ', $matches),
                $this->maxCandidates,
            ),
            $parameters,
        );

        return array_map(static fn (array $row): SuggestionCandidate => new SuggestionCandidate(
            BinaryUuid::fromDatabase(DbalColumn::string($row['id'])),
            DbalColumn::string($row['name']),
            DbalColumn::nullableString($row['brand']),
            1 === DbalColumn::int($row['available']),
        ), $rows);
    }
}
