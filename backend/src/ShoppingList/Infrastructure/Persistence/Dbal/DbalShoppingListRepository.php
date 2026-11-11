<?php

declare(strict_types=1);

namespace App\ShoppingList\Infrastructure\Persistence\Dbal;

use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Shared\Infrastructure\Persistence\Dbal\DbalDateTime;
use App\ShoppingList\Domain\Model\FreeTextItem;
use App\ShoppingList\Domain\Model\ListItem;
use App\ShoppingList\Domain\Model\Member;
use App\ShoppingList\Domain\Model\ProductItem;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Port\ShoppingListChangedConcurrently;
use App\ShoppingList\Domain\Port\ShoppingListRepository;
use App\ShoppingList\Domain\ValueObject\ItemText;
use App\ShoppingList\Domain\ValueObject\ListItemId;
use App\ShoppingList\Domain\ValueObject\ListName;
use App\ShoppingList\Domain\ValueObject\ProductId;
use App\ShoppingList\Domain\ValueObject\Quantity;
use App\ShoppingList\Domain\ValueObject\ShareToken;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Stores the aggregate in shopping_list, list_member and list_item. Saving a loaded list
 * updates its row only while the stored version is still the loaded one (optimistic lock,
 * PD-43), then rewrites its members and items in the same transaction.
 */
final readonly class DbalShoppingListRepository implements ShoppingListRepository
{
    private const string FREE_TEXT = 'free_text';
    private const string PRODUCT = 'product';

    public function __construct(private Connection $connection)
    {
    }

    public function save(ShoppingList $list): void
    {
        $this->connection->transactional(function (Connection $connection) use ($list): void {
            $id = BinaryUuid::toDatabase($list->id()->value());
            $mutable = [
                'share_token' => $list->shareToken()->value(),
                'name' => $list->name()->value(),
                'version' => $list->version(),
                'updated_at' => DbalDateTime::toDatabase($list->updatedAt()),
            ];

            if (0 === $list->loadedVersion()) {
                $connection->insert(
                    'shopping_list',
                    ['id' => $id, 'owner_id' => BinaryUuid::toDatabase($list->ownerId()->value()), ...$mutable, 'created_at' => DbalDateTime::toDatabase($list->createdAt())],
                    ['id' => ParameterType::BINARY, 'owner_id' => ParameterType::BINARY, 'version' => ParameterType::INTEGER],
                );
            } else {
                $updated = $connection->update(
                    'shopping_list',
                    $mutable,
                    ['id' => $id, 'version' => $list->loadedVersion()],
                    ['id' => ParameterType::BINARY, 'version' => ParameterType::INTEGER],
                );
                if (1 !== (int) $updated) {
                    throw ShoppingListChangedConcurrently::withId($list->id()->value());
                }
                $connection->delete('list_member', ['list_id' => $id], ['list_id' => ParameterType::BINARY]);
                $connection->delete('list_item', ['list_id' => $id], ['list_id' => ParameterType::BINARY]);
            }

            foreach ($list->members() as $member) {
                $connection->insert(
                    'list_member',
                    ['list_id' => $id, 'user_id' => BinaryUuid::toDatabase($member->userId->value()), 'joined_at' => DbalDateTime::toDatabase($member->joinedAt)],
                    ['list_id' => ParameterType::BINARY, 'user_id' => ParameterType::BINARY],
                );
            }
            foreach ($list->items() as $position => $item) {
                $this->insertItem($connection, $id, $position, $item);
            }
        });
    }

    public function remove(ShoppingList $list): void
    {
        // Members and items go with it (ON DELETE CASCADE).
        $this->connection->delete('shopping_list', ['id' => BinaryUuid::toDatabase($list->id()->value())], ['id' => ParameterType::BINARY]);
    }

    public function findById(ShoppingListId $id): ?ShoppingList
    {
        return $this->findOneWhere('id = ?', BinaryUuid::toDatabase($id->value()), ParameterType::BINARY);
    }

    public function findByShareToken(ShareToken $shareToken): ?ShoppingList
    {
        // share_token is ascii_bin, so the comparison is case-sensitive.
        return $this->findOneWhere('share_token = ?', $shareToken->value(), ParameterType::STRING);
    }

    public function findByUser(UserId $userId): array
    {
        $user = BinaryUuid::toDatabase($userId->value());
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM shopping_list WHERE owner_id = ? UNION SELECT list_id FROM list_member WHERE user_id = ?',
            [$user, $user],
            [ParameterType::BINARY, ParameterType::BINARY],
        );

        $lists = [];
        foreach ($ids as $id) {
            $list = $this->findOneWhere('id = ?', DbalRow::string($id), ParameterType::BINARY);
            if (null !== $list) {
                $lists[] = $list;
            }
        }

        return $lists;
    }

    private function insertItem(Connection $connection, string $listId, int $position, ListItem $item): void
    {
        $content = $item->content();
        $connection->insert(
            'list_item',
            [
                'id' => BinaryUuid::toDatabase($item->id()->value()),
                'list_id' => $listId,
                'type' => $content instanceof ProductItem ? self::PRODUCT : self::FREE_TEXT,
                'text' => $content instanceof FreeTextItem ? $content->text->value() : null,
                'product_id' => $content instanceof ProductItem ? BinaryUuid::toDatabase($content->productId->value()) : null,
                'quantity' => $item->quantity()->value(),
                'checked' => $item->isChecked(),
                'position' => $position,
            ],
            [
                'id' => ParameterType::BINARY,
                'list_id' => ParameterType::BINARY,
                'product_id' => ParameterType::BINARY,
                'quantity' => ParameterType::INTEGER,
                'checked' => ParameterType::BOOLEAN,
                'position' => ParameterType::INTEGER,
            ],
        );
    }

    private function findOneWhere(string $condition, string $value, ParameterType $type): ?ShoppingList
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, owner_id, share_token, name, version, created_at, updated_at FROM shopping_list WHERE '.$condition,
            [$value],
            [$type],
        );
        if (false === $row) {
            return null;
        }

        $id = DbalRow::string($row['id']);

        return ShoppingList::reconstitute(
            ShoppingListId::fromString(BinaryUuid::fromDatabase($id)),
            UserId::fromString(BinaryUuid::fromDatabase(DbalRow::string($row['owner_id']))),
            ListName::fromString(DbalRow::string($row['name'])),
            ShareToken::fromString(DbalRow::string($row['share_token'])),
            $this->membersOf($id),
            $this->itemsOf($id),
            DbalDateTime::fromDatabase(DbalRow::string($row['created_at'])),
            DbalDateTime::fromDatabase(DbalRow::string($row['updated_at'])),
            DbalRow::int($row['version']),
        );
    }

    /**
     * @return list<Member>
     */
    private function membersOf(string $listId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT user_id, joined_at FROM list_member WHERE list_id = ? ORDER BY joined_at, user_id',
            [$listId],
            [ParameterType::BINARY],
        );

        return array_map(
            static fn (array $row): Member => new Member(
                UserId::fromString(BinaryUuid::fromDatabase(DbalRow::string($row['user_id']))),
                DbalDateTime::fromDatabase(DbalRow::string($row['joined_at'])),
            ),
            $rows,
        );
    }

    /**
     * @return list<ListItem>
     */
    private function itemsOf(string $listId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, type, text, product_id, quantity, checked FROM list_item WHERE list_id = ? ORDER BY position',
            [$listId],
            [ParameterType::BINARY],
        );

        return array_map(
            static fn (array $row): ListItem => ListItem::reconstitute(
                ListItemId::fromString(BinaryUuid::fromDatabase(DbalRow::string($row['id']))),
                self::PRODUCT === $row['type']
                    ? new ProductItem(ProductId::fromString(BinaryUuid::fromDatabase(DbalRow::string($row['product_id']))))
                    : new FreeTextItem(ItemText::fromString(DbalRow::string($row['text']))),
                Quantity::of(DbalRow::int($row['quantity'])),
                1 === DbalRow::int($row['checked']),
            ),
            $rows,
        );
    }
}
