<?php

declare(strict_types=1);

namespace App\Tests\Unit\ShoppingList\Application;

use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\CreateList\CreateListCommand;
use App\ShoppingList\Application\CreateList\CreateListHandler;
use App\ShoppingList\Application\EditItems\AddItemCommand;
use App\ShoppingList\Application\EditItems\AddItemHandler;
use App\ShoppingList\Application\JoinList\JoinListCommand;
use App\ShoppingList\Application\JoinList\JoinListHandler;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\SequentialIdGenerator;
use App\Tests\Support\ShoppingList\InMemoryProductCatalog;
use App\Tests\Support\ShoppingList\InMemoryShoppingListRepository;
use App\Tests\Support\ShoppingList\InMemoryUserDirectory;
use App\Tests\Support\ShoppingList\SequentialShareTokenGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Handlers wired to in-memory fakes. Maria owns lists, Joan joins them and Pedro is an outsider.
 */
abstract class ShoppingListHandlersTestCase extends TestCase
{
    protected const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    protected const string JOAN = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    protected const string PEDRO = '0192f3a4-7b1c-7d2e-8f30-000000000003';
    protected const string UNKNOWN_LIST = '0193ffff-0000-7000-8000-000000000099';
    protected const string MILK = '0191bbbb-0000-7000-8000-000000000001';
    protected const string WATER = '0191bbbb-0000-7000-8000-000000000002';
    protected const string BREAD = '0191bbbb-0000-7000-8000-000000000003';
    protected const string GONE = '0191bbbb-0000-7000-8000-000000000004';

    protected InMemoryShoppingListRepository $lists;
    protected InMemoryProductCatalog $catalog;
    protected InMemoryUserDirectory $directory;
    protected SequentialIdGenerator $ids;
    protected SequentialShareTokenGenerator $tokens;
    protected FixedClock $clock;
    protected ListUpdater $updater;

    protected function setUp(): void
    {
        $this->lists = new InMemoryShoppingListRepository();
        $this->catalog = new InMemoryProductCatalog();
        $this->directory = new InMemoryUserDirectory([self::MARIA => 'maria', self::JOAN => 'joan', self::PEDRO => 'pedro']);
        $this->ids = new SequentialIdGenerator();
        $this->tokens = new SequentialShareTokenGenerator();
        $this->clock = FixedClock::at('2026-11-11 10:00:00');
        $this->updater = new ListUpdater($this->lists);
    }

    protected function createList(string $owner = self::MARIA, string $name = 'Compra semanal'): string
    {
        $handler = new CreateListHandler($this->lists, $this->tokens, $this->ids, $this->clock);

        return $handler(new CreateListCommand($owner, $name));
    }

    protected function join(string $userId, string $shareToken): string
    {
        return (new JoinListHandler($this->updater, $this->lists, $this->clock))(new JoinListCommand($userId, $shareToken));
    }

    /**
     * A list owned by Maria that Joan joined.
     */
    protected function sharedList(): string
    {
        $listId = $this->createList();
        $this->join(self::JOAN, $this->list($listId)->shareToken()->value());

        return $listId;
    }

    protected function addText(string $listId, string $text, string $actor = self::MARIA, ?int $quantity = null): string
    {
        return $this->addItem()(new AddItemCommand($actor, $listId, 'text', text: $text, quantity: $quantity));
    }

    protected function addProduct(string $listId, string $productId, string $actor = self::MARIA, ?int $quantity = null): string
    {
        return $this->addItem()(new AddItemCommand($actor, $listId, 'product', productId: $productId, quantity: $quantity));
    }

    protected function addItem(): AddItemHandler
    {
        return new AddItemHandler($this->updater, $this->catalog, $this->ids, $this->clock);
    }

    protected function list(string $listId): ShoppingList
    {
        return $this->lists->findById(ShoppingListId::fromString($listId)) ?? self::fail('The list was not saved.');
    }

    protected static function user(string $id): UserId
    {
        return UserId::fromString($id);
    }

    protected static function at(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC'));
    }
}
