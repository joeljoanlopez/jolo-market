<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\ShoppingList;

use App\Receipts\Application\ListAccess;
use App\Receipts\Application\ReceiptListNotFound;
use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Infrastructure\ShoppingList\ShoppingListListAccess;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\CheckListAccess\CheckListAccessHandler;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\ValueObject\ListName;
use App\ShoppingList\Domain\ValueObject\ShareToken;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;
use App\Tests\Support\ShoppingList\InMemoryShoppingListRepository;
use App\Tests\Support\ShoppingList\SequentialShareTokenGenerator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ShoppingListListAccessTest extends KernelTestCase
{
    private const string LIST = '0193bbbb-0000-7000-8000-000000000001';
    private const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string PEDRO = '0192f3a4-7b1c-7d2e-8f30-000000000003';

    #[Test]
    public function it_asks_the_shopping_list_query_api_and_answers_its_own_not_found(): void
    {
        $lists = new InMemoryShoppingListRepository();
        $lists->save(ShoppingList::create(ShoppingListId::fromString(self::LIST), UserId::fromString(self::MARIA), ListName::fromString('Compra'), ShareToken::fromString(SequentialShareTokenGenerator::nth(1)), new \DateTimeImmutable()));
        $access = new ShoppingListListAccess(new CheckListAccessHandler(new ListUpdater($lists)));

        $access->assertCanAccess(UserId::fromString(self::MARIA), ListId::fromString(self::LIST));

        $this->expectException(ReceiptListNotFound::class);
        $access->assertCanAccess(UserId::fromString(self::PEDRO), ListId::fromString(self::LIST));
    }

    #[Test]
    public function it_is_the_list_access_the_application_gets(): void
    {
        self::assertInstanceOf(ShoppingListListAccess::class, self::getContainer()->get(ListAccess::class));
    }
}
