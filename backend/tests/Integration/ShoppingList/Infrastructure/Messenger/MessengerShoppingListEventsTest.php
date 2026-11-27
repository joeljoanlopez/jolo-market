<?php

declare(strict_types=1);

namespace App\Tests\Integration\ShoppingList\Infrastructure\Messenger;

use App\ShoppingList\Application\ManageList\ShoppingListDeleted;
use App\ShoppingList\Infrastructure\Messenger\MessengerShoppingListEvents;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class MessengerShoppingListEventsTest extends KernelTestCase
{
    #[Test]
    public function a_deleted_list_is_published_on_the_receipts_transport(): void
    {
        self::bootKernel();
        $events = self::getContainer()->get('test.shopping_list.events');
        self::assertInstanceOf(MessengerShoppingListEvents::class, $events);

        $events->listDeleted(new ShoppingListDeleted('0193bbbb-0000-7000-8000-000000000001'));

        $transport = self::getContainer()->get('messenger.transport.receipts');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertEquals([new ShoppingListDeleted('0193bbbb-0000-7000-8000-000000000001')], array_map(static fn ($envelope): object => $envelope->getMessage(), $transport->getSent()));
    }
}
