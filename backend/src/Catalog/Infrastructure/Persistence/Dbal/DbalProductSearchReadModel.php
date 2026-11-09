<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\SearchProducts\ProductSearchPage;
use App\Catalog\Application\SearchProducts\ProductSearchReadModel;
use App\Catalog\Application\SearchProducts\ProductSearchResult;
use App\Catalog\Domain\ValueObject\SupermarketId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Matching uses the FULLTEXT index on product(name, brand) in boolean mode, one required prefix term per word
 * (`+word*`). The columns are utf8mb4_0900_ai_ci, so the index ignores case and accents.
 *
 * InnoDB does not index words shorter than innodb_ft_min_token_size (3) nor its default stopwords ("de", "la",
 * "en"...), and a required term on one of those matches nothing. Those words are matched with LIKE instead, at the
 * start of a word of the name and brand (LIKE also follows the ai_ci collation).
 *
 * The user's text is split on anything that is not a letter or a digit, so boolean mode operators
 * (+ - < > ( ) ~ * " @) never reach MATCH.
 */
final readonly class DbalProductSearchReadModel implements ProductSearchReadModel
{
    private const int MIN_TOKEN_SIZE = 3;

    /** InnoDB's default stopword list (INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD). */
    private const array STOPWORDS = [
        'a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from', 'how', 'i', 'in', 'is',
        'it', 'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who', 'will',
        'with', 'und', 'www',
    ];

    /** Name and brand with punctuation turned into spaces and a leading space, so "% word%" finds a word start. */
    private const string WORDS_OF_PRODUCT = "CONCAT(' ', REGEXP_REPLACE(CONCAT_WS(' ', p.name, p.brand), '[^[:alnum:]]+', ' '))";

    /**
     * PD-33: units with more results first (equally frequent units as kg, l, unit), products without a unit price
     * last, then unit price, shelf price, name and id ascending, so pages are stable.
     */
    private const string ORDER = "p.unit_price_unit IS NULL, unit_group_size DESC, FIELD(p.unit_price_unit, 'kg', 'l', 'unit'),
        p.unit_price_cents, p.price_cents, p.name, p.id";

    public function __construct(private Connection $connection)
    {
    }

    public function search(string $text, array $supermarkets, int $page, int $perPage): ProductSearchPage
    {
        $words = self::words($text);
        if ([] === $words || [] === $supermarkets) {
            return new ProductSearchPage([], 0, $page, $perPage, []);
        }

        [$where, $params, $types] = self::filter($words, $supermarkets);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT p.id, p.name, p.brand, p.supermarket_id, s.name AS supermarket_name, p.package_quantity, p.package_unit,
                    p.price_cents, p.unit_price_cents, p.unit_price_unit, p.image_url,
                    COUNT(*) OVER (PARTITION BY p.unit_price_unit) AS unit_group_size
             FROM product p JOIN supermarket s ON s.id = p.supermarket_id
             WHERE '.$where.'
             ORDER BY '.self::ORDER.'
             LIMIT :limit OFFSET :offset',
            [...$params, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage],
            [...$types, 'limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        );

        $groups = $this->connection->fetchAllAssociative(
            'SELECT p.unit_price_unit, COUNT(*) AS results, MIN(p.unit_price_cents) AS cheapest
             FROM product p WHERE '.$where.' GROUP BY p.unit_price_unit',
            $params,
            $types,
        );

        $total = 0;
        $cheapest = [];
        foreach ($groups as $group) {
            $total += DbalColumn::int($group['results']);
            $unit = DbalColumn::nullableString($group['unit_price_unit']);
            if (null !== $unit) {
                $cheapest[$unit] = DbalColumn::int($group['cheapest']);
            }
        }

        return new ProductSearchPage(array_map(self::hydrate(...), $rows), $total, $page, $perPage, $cheapest);
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, \PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(false === $words ? [] : $words));
    }

    /**
     * @param non-empty-list<string>        $words
     * @param non-empty-list<SupermarketId> $supermarkets
     *
     * @return array{string, array<string, mixed>, array<string, ArrayParameterType|ParameterType>}
     */
    private static function filter(array $words, array $supermarkets): array
    {
        $conditions = ['p.available = 1', 'p.supermarket_id IN (:supermarkets)'];
        $params = ['supermarkets' => array_map(static fn (SupermarketId $id): string => $id->value(), $supermarkets)];
        $types = ['supermarkets' => ArrayParameterType::STRING];

        $indexedTerms = [];
        foreach ($words as $index => $word) {
            if (mb_strlen($word) >= self::MIN_TOKEN_SIZE && !\in_array($word, self::STOPWORDS, true)) {
                $indexedTerms[] = '+'.$word.'*';
                continue;
            }
            $conditions[] = self::WORDS_OF_PRODUCT.' LIKE :word'.$index;
            $params['word'.$index] = '% '.$word.'%';
        }

        if ([] !== $indexedTerms) {
            $conditions[] = 'MATCH (p.name, p.brand) AGAINST (:terms IN BOOLEAN MODE)';
            $params['terms'] = implode(' ', $indexedTerms);
        }

        return [implode(' AND ', $conditions), $params, $types];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): ProductSearchResult
    {
        return new ProductSearchResult(
            BinaryUuid::fromDatabase(DbalColumn::string($row['id'])),
            DbalColumn::string($row['name']),
            DbalColumn::nullableString($row['brand']),
            DbalColumn::string($row['supermarket_id']),
            DbalColumn::string($row['supermarket_name']),
            DbalColumn::nullableInt($row['package_quantity']),
            DbalColumn::nullableString($row['package_unit']),
            DbalColumn::int($row['price_cents']),
            DbalColumn::nullableInt($row['unit_price_cents']),
            DbalColumn::nullableString($row['unit_price_unit']),
            DbalColumn::nullableString($row['image_url']),
        );
    }
}
