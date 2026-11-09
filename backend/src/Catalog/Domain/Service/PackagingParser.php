<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use App\Catalog\Domain\ValueObject\InvalidPackaging;
use App\Catalog\Domain\ValueObject\Packaging;

/**
 * Turns the packaging text a supermarket shows ("6 x 1 L", "pack 12 ud") into a Packaging.
 *
 * Only strings that match a known format completely are accepted. Anything else (approximate
 * weights, ranges, unknown words) yields null, the explicit "unknown" state, because a wrong
 * quantity would rank a product with a made-up unit price.
 */
final class PackagingParser
{
    private const string PATTERN = '/^(?:pack(?: de)? )?(?:(?<items>\d+) ?[x×] ?)?(?<quantity>\d+(?:[.,]\d+)?) ?(?<unit>[a-z]+)$/Du';

    /** @var array<string, 'kg'|'g'|'l'|'cl'|'ml'|'ud'> */
    private const array UNIT_ALIASES = [
        'kg' => 'kg',
        'g' => 'g',
        'gr' => 'g',
        'l' => 'l',
        'lt' => 'l',
        'litro' => 'l',
        'litros' => 'l',
        'cl' => 'cl',
        'ml' => 'ml',
        'u' => 'ud',
        'ud' => 'ud',
        'uds' => 'ud',
        'unidad' => 'ud',
        'unidades' => 'ud',
    ];

    public function parse(string $source): ?Packaging
    {
        $normalized = (string) preg_replace('/\s+/u', ' ', mb_strtolower(trim($source)));

        if (1 !== preg_match(self::PATTERN, $normalized, $matches)) {
            return null;
        }

        $unit = self::UNIT_ALIASES[$matches['unit']] ?? null;

        if (null === $unit) {
            return null;
        }

        try {
            $packaging = $this->single(str_replace(',', '.', $matches['quantity']), $unit);

            return '' === $matches['items'] ? $packaging : $packaging?->multipliedBy((int) $matches['items']);
        } catch (InvalidPackaging) {
            return null;
        }
    }

    /**
     * @param 'kg'|'g'|'l'|'cl'|'ml'|'ud' $unit
     */
    private function single(string $quantity, string $unit): ?Packaging
    {
        return match ($unit) {
            'kg' => Packaging::kilograms($quantity),
            'g' => Packaging::grams($quantity),
            'l' => Packaging::litres($quantity),
            'cl' => Packaging::centilitres($quantity),
            'ml' => Packaging::millilitres($quantity),
            'ud' => ctype_digit($quantity) ? Packaging::units((int) $quantity) : null,
        };
    }
}
