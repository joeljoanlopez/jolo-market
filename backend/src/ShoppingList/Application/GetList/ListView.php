<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\GetList;

/**
 * The list as its owner or a member sees it. $shareToken is null for members.
 */
final readonly class ListView
{
    public const string OWNER = 'owner';
    public const string MEMBER = 'member';

    /**
     * @param list<ListUserView>     $members in the order they joined
     * @param list<ListItemView>     $items   in position order
     * @param list<SupermarketTotal> $totals  ordered by supermarket name
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public int $version,
        public ?string $shareToken,
        public ListUserView $owner,
        public array $members,
        public array $items,
        public array $totals,
    ) {
    }
}
