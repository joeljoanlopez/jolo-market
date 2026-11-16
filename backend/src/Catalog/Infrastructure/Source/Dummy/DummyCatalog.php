<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Source\Dummy;

use App\Catalog\Domain\Port\SourceProduct;

/**
 * The hand-written catalog of the dummy sources (PD-54), only for the development environment.
 *
 * One list of products is shared by the three chains, so the same product can be compared
 * across them; each chain has its own price and some products are sold by one chain only.
 * It covers every case the app handles: prices per kg, L and unit, packs, a product without
 * unit price (packaging we cannot parse), approximate sizes priced by the store's reference
 * price (PD-29) and one malformed product per chain (PD-23).
 *
 * Everything depends only on the chain and the run date, so the same day always returns the
 * same data. Products marked "daily" or "weekly" change price over time, and products marked
 * "intermittent" are missing one day out of five, so ingestion marks them unavailable and they
 * come back the next day (PD-22, PD-25).
 */
final class DummyCatalog
{
    public const array SUPERMARKETS = ['mercadona', 'bonpreu', 'aldi'];

    /** Price changes, in percent of the base price, that a volatile product cycles through. */
    private const array PRICE_STEPS = [0, 3, 6, 3, 0, -3, -6, -3];

    /** An intermittent product is missing on the days whose number modulo this is its offset. */
    private const int INTERMITTENT_CYCLE = 5;

    private const string DAILY = 'daily';
    private const string WEEKLY = 'weekly';

    /**
     * name, packaging, category path, price per chain (null: not sold there), brand and options:
     * "reference": [unit, price per chain] for approximate sizes (PD-29), "volatility": daily or
     * weekly, "intermittent": true.
     *
     * @var list<array{
     *     name: string,
     *     packaging: ?string,
     *     category: list<string>,
     *     prices: array{mercadona: ?int, bonpreu: ?int, aldi: ?int},
     *     brand?: string,
     *     reference?: array{unit: 'kg'|'l'|'unit', prices: array{mercadona: ?int, bonpreu: ?int, aldi: ?int}},
     *     volatility?: 'daily'|'weekly',
     *     intermittent?: true,
     * }>
     */
    private const array PRODUCTS = [
        // Dairy and eggs
        ['name' => 'Leche entera', 'packaging' => '1 L', 'category' => ['Lácteos y huevos', 'Leche'], 'prices' => ['mercadona' => 95, 'bonpreu' => 105, 'aldi' => 89], 'volatility' => self::WEEKLY],
        ['name' => 'Leche semidesnatada', 'packaging' => '6 x 1 L', 'category' => ['Lácteos y huevos', 'Leche'], 'prices' => ['mercadona' => 552, 'bonpreu' => 600, 'aldi' => 534]],
        ['name' => 'Yogur natural', 'packaging' => 'pack 4 x 125 g', 'category' => ['Lácteos y huevos', 'Yogures'], 'prices' => ['mercadona' => 85, 'bonpreu' => 99, 'aldi' => 79]],
        ['name' => 'Huevos frescos L', 'packaging' => '12 ud', 'category' => ['Lácteos y huevos', 'Huevos'], 'prices' => ['mercadona' => 249, 'bonpreu' => 275, 'aldi' => 239], 'volatility' => self::WEEKLY],
        ['name' => 'Queso curado de oveja', 'packaging' => '250 g', 'category' => ['Lácteos y huevos', 'Quesos'], 'prices' => ['mercadona' => 399, 'bonpreu' => 450, 'aldi' => 379], 'brand' => 'Dehesa Alta'],
        ['name' => 'Mantequilla', 'packaging' => '250 g', 'category' => ['Lácteos y huevos', 'Mantequilla'], 'prices' => ['mercadona' => 215, 'bonpreu' => 239, 'aldi' => 199]],
        // Pantry
        ['name' => 'Aceite de oliva virgen extra', 'packaging' => '1 L', 'category' => ['Despensa', 'Aceites'], 'prices' => ['mercadona' => 895, 'bonpreu' => 949, 'aldi' => 869], 'brand' => 'Sol de Olivo', 'volatility' => self::DAILY],
        ['name' => 'Aceite de girasol', 'packaging' => '1 L', 'category' => ['Despensa', 'Aceites'], 'prices' => ['mercadona' => 189, 'bonpreu' => 199, 'aldi' => 175]],
        ['name' => 'Arroz redondo', 'packaging' => '1 kg', 'category' => ['Despensa', 'Arroz y legumbres'], 'prices' => ['mercadona' => 129, 'bonpreu' => 145, 'aldi' => 119]],
        ['name' => 'Espaguetis', 'packaging' => '500 g', 'category' => ['Despensa', 'Pasta'], 'prices' => ['mercadona' => 85, 'bonpreu' => 95, 'aldi' => 79]],
        ['name' => 'Garbanzos cocidos', 'packaging' => '400 g', 'category' => ['Despensa', 'Arroz y legumbres'], 'prices' => ['mercadona' => 75, 'bonpreu' => 89, 'aldi' => 69]],
        ['name' => 'Atún claro en aceite de oliva', 'packaging' => 'pack 3 x 80 g', 'category' => ['Despensa', 'Conservas'], 'prices' => ['mercadona' => 299, 'bonpreu' => 325, 'aldi' => 285], 'brand' => 'Mar Azul'],
        ['name' => 'Tomate frito', 'packaging' => '400 g', 'category' => ['Despensa', 'Salsas'], 'prices' => ['mercadona' => 89, 'bonpreu' => 99, 'aldi' => 79]],
        ['name' => 'Café molido natural', 'packaging' => '250 g', 'category' => ['Despensa', 'Café e infusiones'], 'prices' => ['mercadona' => 279, 'bonpreu' => 299, 'aldi' => 265], 'volatility' => self::WEEKLY],
        ['name' => 'Azúcar blanco', 'packaging' => '1 kg', 'category' => ['Despensa', 'Azúcar y edulcorantes'], 'prices' => ['mercadona' => 119, 'bonpreu' => 125, 'aldi' => 109]],
        ['name' => 'Sal fina', 'packaging' => '1 kg', 'category' => ['Despensa', 'Sal y especias'], 'prices' => ['mercadona' => 35, 'bonpreu' => 39, 'aldi' => 29]],
        ['name' => 'Galletas María', 'packaging' => '800 g', 'category' => ['Despensa', 'Galletas'], 'prices' => ['mercadona' => 165, 'bonpreu' => 179, 'aldi' => 149]],
        ['name' => 'Chocolate negro 70 %', 'packaging' => '200 g', 'category' => ['Despensa', 'Chocolates'], 'prices' => ['mercadona' => null, 'bonpreu' => null, 'aldi' => 149], 'brand' => 'Cacao Real', 'intermittent' => true],
        // Drinks
        ['name' => 'Agua mineral', 'packaging' => '6 x 1,5 L', 'category' => ['Bebidas', 'Agua'], 'prices' => ['mercadona' => 234, 'bonpreu' => 270, 'aldi' => 210]],
        ['name' => 'Cerveza lager', 'packaging' => 'pack 6 x 33 cl', 'category' => ['Bebidas', 'Cerveza'], 'prices' => ['mercadona' => 330, 'bonpreu' => 360, 'aldi' => 299], 'brand' => 'Cervezas del Norte', 'volatility' => self::WEEKLY],
        ['name' => 'Zumo de naranja', 'packaging' => '1 L', 'category' => ['Bebidas', 'Zumos'], 'prices' => ['mercadona' => 139, 'bonpreu' => 155, 'aldi' => 125]],
        ['name' => 'Refresco de cola', 'packaging' => '2 L', 'category' => ['Bebidas', 'Refrescos'], 'prices' => ['mercadona' => 125, 'bonpreu' => 145, 'aldi' => 115]],
        ['name' => 'Gazpacho tradicional', 'packaging' => '1 L', 'category' => ['Bebidas', 'Gazpacho'], 'prices' => ['mercadona' => 235, 'bonpreu' => null, 'aldi' => null], 'intermittent' => true],
        // Fruit and vegetables
        ['name' => 'Plátano de Canarias', 'packaging' => 'aprox. 1 kg', 'category' => ['Fruta y verdura', 'Fruta'], 'prices' => ['mercadona' => 235, 'bonpreu' => 259, 'aldi' => 219], 'reference' => ['unit' => 'kg', 'prices' => ['mercadona' => 235, 'bonpreu' => 259, 'aldi' => 219]], 'volatility' => self::DAILY],
        ['name' => 'Tomate en rama', 'packaging' => 'aprox. 750 g', 'category' => ['Fruta y verdura', 'Verdura'], 'prices' => ['mercadona' => 194, 'bonpreu' => 209, 'aldi' => 179], 'reference' => ['unit' => 'kg', 'prices' => ['mercadona' => 259, 'bonpreu' => 279, 'aldi' => 239]], 'volatility' => self::DAILY],
        ['name' => 'Manzana golden', 'packaging' => 'aprox. 1,5 kg', 'category' => ['Fruta y verdura', 'Fruta'], 'prices' => ['mercadona' => 299, 'bonpreu' => 329, 'aldi' => 285], 'reference' => ['unit' => 'kg', 'prices' => ['mercadona' => 199, 'bonpreu' => 219, 'aldi' => 190]]],
        ['name' => 'Patatas', 'packaging' => '3 kg', 'category' => ['Fruta y verdura', 'Verdura'], 'prices' => ['mercadona' => 349, 'bonpreu' => 375, 'aldi' => 329]],
        ['name' => 'Cebollas', 'packaging' => '2 kg', 'category' => ['Fruta y verdura', 'Verdura'], 'prices' => ['mercadona' => 199, 'bonpreu' => 215, 'aldi' => 185]],
        ['name' => 'Lechuga iceberg', 'packaging' => '1 ud', 'category' => ['Fruta y verdura', 'Verdura'], 'prices' => ['mercadona' => 89, 'bonpreu' => 99, 'aldi' => 79]],
        ['name' => 'Aguacates', 'packaging' => 'malla 2 piezas', 'category' => ['Fruta y verdura', 'Fruta'], 'prices' => ['mercadona' => 249, 'bonpreu' => 275, 'aldi' => 229]],
        // Bakery
        ['name' => 'Pan de molde integral', 'packaging' => '600 g', 'category' => ['Panadería', 'Pan de molde'], 'prices' => ['mercadona' => 139, 'bonpreu' => 155, 'aldi' => 125]],
        ['name' => 'Barra de pan', 'packaging' => '1 ud', 'category' => ['Panadería', 'Pan'], 'prices' => ['mercadona' => 45, 'bonpreu' => 55, 'aldi' => 39]],
        ['name' => 'Croissants', 'packaging' => 'pack 6 ud', 'category' => ['Panadería', 'Bollería'], 'prices' => ['mercadona' => 199, 'bonpreu' => 225, 'aldi' => 179]],
        // Meat and fish
        ['name' => 'Pechuga de pollo fileteada', 'packaging' => 'aprox. 600 g', 'category' => ['Carne y pescado', 'Pollo'], 'prices' => ['mercadona' => 419, 'bonpreu' => 449, 'aldi' => 399], 'reference' => ['unit' => 'kg', 'prices' => ['mercadona' => 699, 'bonpreu' => 749, 'aldi' => 665]], 'volatility' => self::WEEKLY],
        ['name' => 'Lomo de salmón', 'packaging' => '250 g', 'category' => ['Carne y pescado', 'Pescado'], 'prices' => ['mercadona' => 449, 'bonpreu' => 499, 'aldi' => null], 'intermittent' => true],
        ['name' => 'Fuet', 'packaging' => '175 g', 'category' => ['Carne y pescado', 'Embutidos'], 'prices' => ['mercadona' => null, 'bonpreu' => 295, 'aldi' => null], 'brand' => 'Embotits de la Plana', 'intermittent' => true],
        // Household and personal care
        ['name' => 'Detergente líquido', 'packaging' => '40 lavados', 'category' => ['Limpieza y hogar', 'Detergente'], 'prices' => ['mercadona' => 650, 'bonpreu' => 699, 'aldi' => 599]],
        ['name' => 'Papel higiénico doble capa', 'packaging' => '12 rollos', 'category' => ['Limpieza y hogar', 'Papel'], 'prices' => ['mercadona' => 399, 'bonpreu' => 435, 'aldi' => 379]],
        ['name' => 'Lavavajillas a mano', 'packaging' => '750 ml', 'category' => ['Limpieza y hogar', 'Lavavajillas'], 'prices' => ['mercadona' => 99, 'bonpreu' => 115, 'aldi' => 89]],
        ['name' => 'Gel de ducha', 'packaging' => '750 ml', 'category' => ['Cuidado personal', 'Gel'], 'prices' => ['mercadona' => 149, 'bonpreu' => 169, 'aldi' => 135]],
    ];

    /**
     * The products the chain returns on the day of $now, in a stable order.
     *
     * @return list<SourceProduct>
     */
    public static function forSupermarket(string $supermarket, \DateTimeImmutable $now): array
    {
        if (!\in_array($supermarket, self::SUPERMARKETS, true)) {
            throw new \InvalidArgumentException(\sprintf('There is no dummy catalog for supermarket "%s".', $supermarket));
        }

        $day = self::dayNumber($now);
        $products = [];
        foreach (self::PRODUCTS as $index => $row) {
            $basePrice = $row['prices'][$supermarket];
            if (null === $basePrice || self::isMissing($row, $index, $day)) {
                continue;
            }

            $percent = self::priceChangeInPercent($row, $index, $day);
            [$referencePrice, $referenceUnit] = self::reference($row, $supermarket, $percent);

            $products[] = new SourceProduct(
                self::externalId($index),
                $row['name'],
                self::adjusted($basePrice, $percent),
                $row['packaging'],
                $row['brand'] ?? null,
                $row['category'],
                null,
                $referencePrice,
                $referenceUnit,
            );
        }

        // Malformed on purpose: ingestion skips it and counts it (PD-23).
        $products[] = new SourceProduct('dummy-malformed', ' ', 199, '1 ud', null, ['Varios']);

        return $products;
    }

    /**
     * @param array{reference?: array{unit: 'kg'|'l'|'unit', prices: array<string, ?int>}} $row
     *
     * @return array{?int, ?string} the store's price per unit of an approximate size (PD-29), and that unit
     */
    private static function reference(array $row, string $supermarket, int $percent): array
    {
        $price = $row['reference']['prices'][$supermarket] ?? null;

        return null === $price || !isset($row['reference']) ? [null, null] : [self::adjusted($price, $percent), $row['reference']['unit']];
    }

    private static function externalId(int $index): string
    {
        return \sprintf('dummy-%03d', $index + 1);
    }

    /**
     * Days since the Unix epoch, in UTC.
     */
    private static function dayNumber(\DateTimeImmutable $now): int
    {
        return intdiv($now->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0)->getTimestamp(), 86_400);
    }

    /**
     * @param array{intermittent?: true} $row
     */
    private static function isMissing(array $row, int $index, int $day): bool
    {
        return isset($row['intermittent']) && $index % self::INTERMITTENT_CYCLE === $day % self::INTERMITTENT_CYCLE;
    }

    /**
     * @param array{volatility?: 'daily'|'weekly'} $row
     */
    private static function priceChangeInPercent(array $row, int $index, int $day): int
    {
        $period = match ($row['volatility'] ?? null) {
            self::DAILY => $day,
            self::WEEKLY => intdiv($day, 7),
            null => null,
        };

        return null === $period ? 0 : self::PRICE_STEPS[($period + $index) % \count(self::PRICE_STEPS)];
    }

    private static function adjusted(int $cents, int $percent): int
    {
        return intdiv($cents * (100 + $percent) + 50, 100);
    }
}
