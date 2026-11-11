<?php

declare(strict_types=1);

namespace App\ShoppingList\Infrastructure\Api;

use App\ShoppingList\Application\GetList\ListItemView;
use App\ShoppingList\Application\GetList\ListUserView;
use App\ShoppingList\Application\GetList\ListView;
use App\ShoppingList\Application\GetList\SupermarketTotal;

/**
 * The JSON of GET /api/lists/{id} (docs/architecture.md, HTTP API).
 */
final class ListJson
{
    /**
     * @return array<string, mixed>
     */
    public static function of(ListView $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'role' => $list->role,
            'version' => $list->version,
            'shareToken' => $list->shareToken,
            'owner' => self::user($list->owner),
            'members' => array_map(self::user(...), $list->members),
            'items' => array_map(self::item(...), $list->items),
            'totals' => array_map(
                static fn (SupermarketTotal $total): array => [
                    'supermarketId' => $total->supermarketId,
                    'supermarketName' => $total->supermarketName,
                    'totalCents' => $total->totalCents,
                    'itemCount' => $total->itemCount,
                ],
                $list->totals,
            ),
        ];
    }

    /**
     * @return array{id: string, username: string}
     */
    private static function user(ListUserView $user): array
    {
        return ['id' => $user->id, 'username' => $user->username];
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(ListItemView $item): array
    {
        $product = $item->product;

        return [
            'id' => $item->id,
            'position' => $item->position,
            'quantity' => $item->quantity,
            'checked' => $item->checked,
            'type' => $item->type,
            'text' => $item->text,
            'product' => null === $product ? null : [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'supermarketId' => $product->supermarketId,
                'priceCents' => $product->priceCents,
                'imageUrl' => $product->imageUrl,
                'available' => $product->available,
            ],
        ];
    }
}
