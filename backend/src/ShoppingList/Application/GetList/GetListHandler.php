<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\GetList;

use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\CatalogProduct;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Application\ProductCatalog;
use App\ShoppingList\Application\UserDirectory;
use App\ShoppingList\Domain\Model\FreeTextItem;
use App\ShoppingList\Domain\Model\ListItem;
use App\ShoppingList\Domain\Model\Member;
use App\ShoppingList\Domain\Model\ProductItem;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Model\ShoppingListNotFound;
use App\ShoppingList\Domain\ValueObject\ProductId;

/**
 * The list with fresh product data and per-supermarket totals (E4.10). Unavailable products are
 * shown flagged and left out of the totals (PD-22).
 */
final readonly class GetListHandler
{
    public function __construct(
        private ListUpdater $updater,
        private ProductCatalog $catalog,
        private UserDirectory $users,
    ) {
    }

    public function __invoke(GetListQuery $query): ListView
    {
        $list = $this->updater->load($query->listId);
        $actor = UserId::fromString($query->actorId);
        if (!$list->canBeAccessedBy($actor)) {
            throw ShoppingListNotFound::withId($query->listId);
        }

        $isOwner = $list->isOwnedBy($actor);
        $products = $this->productsOf($list);
        $usernames = $this->users->usernamesOf([$list->ownerId(), ...array_map(static fn (Member $member): UserId => $member->userId, $list->members())]);
        $user = static fn (UserId $id): ListUserView => new ListUserView($id->value(), $usernames[$id->value()] ?? '');

        $items = [];
        foreach ($list->items() as $position => $item) {
            $items[] = self::itemView($item, $position, $products);
        }

        return new ListView(
            $list->id()->value(),
            $list->name()->value(),
            $isOwner ? ListView::OWNER : ListView::MEMBER,
            $list->version(),
            $isOwner ? $list->shareToken()->value() : null,
            $user($list->ownerId()),
            array_map(static fn (Member $member): ListUserView => $user($member->userId), $list->members()),
            $items,
            self::totals($list, $products),
        );
    }

    /**
     * @return array<string, CatalogProduct>
     */
    private function productsOf(ShoppingList $list): array
    {
        $ids = [];
        foreach ($list->items() as $item) {
            $content = $item->content();
            if ($content instanceof ProductItem) {
                $ids[$content->productId->value()] = $content->productId;
            }
        }

        return [] === $ids ? [] : $this->catalog->findByIds(array_values($ids));
    }

    /**
     * @param array<string, CatalogProduct> $products
     */
    private static function itemView(ListItem $item, int $position, array $products): ListItemView
    {
        $content = $item->content();
        $text = $content instanceof FreeTextItem ? $content->text->value() : null;
        $product = $content instanceof ProductItem ? self::productView($content->productId, $products) : null;

        return new ListItemView(
            $item->id()->value(),
            $position,
            $item->quantity()->value(),
            $item->isChecked(),
            null === $product ? ListItemView::TEXT : ListItemView::PRODUCT,
            $text,
            $product,
        );
    }

    /**
     * @param array<string, CatalogProduct> $products
     */
    private static function productView(ProductId $id, array $products): ListProductView
    {
        $product = $products[$id->value()] ?? null;
        if (null === $product) {
            return new ListProductView($id->value(), null, null, null, null, null, false);
        }

        return new ListProductView($id->value(), $product->name, $product->brand, $product->supermarketId, $product->priceCents, $product->imageUrl, $product->available);
    }

    /**
     * @param array<string, CatalogProduct> $products
     *
     * @return list<SupermarketTotal>
     */
    private static function totals(ShoppingList $list, array $products): array
    {
        /** @var array<string, array{name: string, total: Money, count: int}> $bySupermarket */
        $bySupermarket = [];
        foreach ($list->items() as $item) {
            $content = $item->content();
            $product = $content instanceof ProductItem ? ($products[$content->productId->value()] ?? null) : null;
            if (null === $product || !$product->available) {
                continue;
            }

            $current = $bySupermarket[$product->supermarketId] ?? ['name' => $product->supermarketName, 'total' => Money::eur(0), 'count' => 0];
            $bySupermarket[$product->supermarketId] = [
                'name' => $current['name'],
                'total' => $current['total']->add(Money::eur($product->priceCents)->multiply($item->quantity()->value())),
                'count' => $current['count'] + 1,
            ];
        }

        $totals = [];
        foreach ($bySupermarket as $supermarketId => $total) {
            $totals[] = new SupermarketTotal($supermarketId, $total['name'], $total['total']->cents(), $total['count']);
        }
        usort($totals, static fn (SupermarketTotal $a, SupermarketTotal $b): int => strcmp($a->supermarketName, $b->supermarketName));

        return $totals;
    }
}
