<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use App\Catalog\Domain\Model\Section;
use App\Catalog\Domain\ValueObject\CategoryPath;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Turns a supermarket's category path into a section by its longest matching prefix of whole
 * segments (PD-91). A missing or unknown path is "other". It is applied when a product is read,
 * so extending these rules moves the items already in lists.
 */
final readonly class SectionMapping
{
    /**
     * The categories of the dummy catalog (E8, PD-115). The dummy sources use the real chains'
     * ids, so these rules apply to all three.
     *
     * @var list<array{list<string>, Section}>
     */
    private const array DUMMY_RULES = [
        [['Fruta y verdura'], Section::FRUIT_AND_VEGETABLES],
        [['Panadería'], Section::BAKERY],
        [['Carne y pescado'], Section::MEAT_AND_FISH],
        [['Carne y pescado', 'Embutidos'], Section::DELI_AND_CHEESE],
        [['Lácteos y huevos'], Section::DAIRY_AND_EGGS],
        [['Lácteos y huevos', 'Quesos'], Section::DELI_AND_CHEESE],
        [['Despensa'], Section::PANTRY],
        [['Bebidas'], Section::DRINKS],
        [['Limpieza y hogar'], Section::HOUSEHOLD],
        [['Cuidado personal'], Section::PERSONAL_CARE],
    ];

    /**
     * Rules of each chain's own category tree, on top of the dummy ones.
     *
     * @var array<string, list<array{list<string>, Section}>>
     */
    private const array CHAIN_RULES = [
        'mercadona' => [],
        'bonpreu' => [],
        'aldi' => [],
    ];

    public function sectionOf(SupermarketId $supermarketId, ?CategoryPath $category): Section
    {
        if (null === $category || !\array_key_exists($supermarketId->value(), self::CHAIN_RULES)) {
            return Section::OTHER;
        }

        $segments = $category->segments();
        $section = Section::OTHER;
        $longest = 0;
        foreach ([...self::DUMMY_RULES, ...self::CHAIN_RULES[$supermarketId->value()]] as [$prefix, $candidate]) {
            $length = \count($prefix);
            if ($length > $longest && \array_slice($segments, 0, $length) === $prefix) {
                $section = $candidate;
                $longest = $length;
            }
        }

        return $section;
    }
}
