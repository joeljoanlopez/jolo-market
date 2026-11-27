<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\SuggestProducts\SuggestionCandidate;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Persistence\Dbal\DbalSuggestionCandidates;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;

final class DbalSuggestionCandidatesTest extends DbalTestCase
{
    #[Test]
    public function it_returns_the_products_of_the_supermarket_containing_any_word_available_or_not(): void
    {
        $this->insert(1, 'mercadona', 'Leche entera', 'Hacendado', true);
        $this->insert(2, 'mercadona', 'Pan de molde', 'Hacendado', false);
        $this->insert(3, 'mercadona', 'Detergente', 'Bosque Verde', true);
        $this->insert(4, 'aldi', 'Leche entera', null, true);

        $candidates = (new DbalSuggestionCandidates($this->connection))->matchingAnyWord(SupermarketId::fromString('mercadona'), ['leche', 'hacend']);

        self::assertEquals([
            new SuggestionCandidate(self::id(1), 'Leche entera', 'Hacendado', true),
            new SuggestionCandidate(self::id(2), 'Pan de molde', 'Hacendado', false),
        ], $candidates);
    }

    #[Test]
    public function accents_do_not_matter(): void
    {
        $this->insert(1, 'aldi', 'Plátano de Canarias', null, true);

        $candidates = (new DbalSuggestionCandidates($this->connection))->matchingAnyWord(SupermarketId::fromString('aldi'), ['platano']);

        self::assertCount(1, $candidates);
    }

    #[Test]
    public function it_keeps_the_products_with_more_matching_words_when_it_cuts_the_list(): void
    {
        $this->insert(1, 'mercadona', 'Agua mineral', null, true);
        $this->insert(2, 'mercadona', 'Agua con gas', null, true);

        $candidates = (new DbalSuggestionCandidates($this->connection, 1))->matchingAnyWord(SupermarketId::fromString('mercadona'), ['agua', 'gas']);

        self::assertSame([self::id(2)], array_map(static fn (SuggestionCandidate $candidate): string => $candidate->productId, $candidates));
    }

    #[Test]
    public function a_percent_sign_or_underscore_is_matched_literally(): void
    {
        $this->insert(1, 'mercadona', 'Leche entera', null, true);

        self::assertSame([], (new DbalSuggestionCandidates($this->connection))->matchingAnyWord(SupermarketId::fromString('mercadona'), ['%', '_']));
    }

    private static function id(int $n): string
    {
        return \sprintf('00000000-0000-4000-8000-%012d', $n);
    }

    private function insert(int $n, string $supermarketId, string $name, ?string $brand, bool $available): void
    {
        $this->connection->insert(
            'product',
            ['id' => BinaryUuid::toDatabase(self::id($n)), 'supermarket_id' => $supermarketId, 'external_id' => (string) $n, 'name' => $name, 'brand' => $brand,
                'price_cents' => 100, 'available' => $available, 'last_seen_at' => '2026-11-08 06:00:00'],
            ['id' => ParameterType::BINARY, 'available' => ParameterType::BOOLEAN],
        );
    }
}
