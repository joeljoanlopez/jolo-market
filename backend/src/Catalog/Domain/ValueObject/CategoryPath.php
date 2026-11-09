<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Where a product sits in its supermarket's category tree, from the root down.
 */
final readonly class CategoryPath
{
    private const string SEPARATOR = ' > ';

    /**
     * @param non-empty-list<string> $segments
     */
    private function __construct(private array $segments)
    {
    }

    /**
     * @param list<string> $segments
     */
    public static function fromSegments(array $segments): self
    {
        $kept = array_values(array_filter(
            array_map(trim(...), $segments),
            static fn (string $segment): bool => '' !== $segment,
        ));

        if ([] === $kept) {
            throw InvalidProductData::emptyCategoryPath();
        }

        return new self($kept);
    }

    /**
     * @return non-empty-list<string>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    public function toString(): string
    {
        return implode(self::SEPARATOR, $this->segments);
    }
}
