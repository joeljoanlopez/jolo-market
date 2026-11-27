<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\Messenger;

use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Port\ReceiptPhotoStorage;
use App\Receipts\Domain\Port\ReceiptRepository;
use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Receipts\Infrastructure\Messenger\ShoppingListDeletedMessageHandler;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ManageList\ShoppingListDeleted;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ShoppingListDeletedMessageHandlerTest extends KernelTestCase
{
    private const string LIST = '0193bbbb-0000-7000-8000-000000000001';
    private const string RECEIPT = '0193aaaa-0000-7000-8000-000000000001';

    #[Test]
    public function the_worker_removes_the_receipts_and_photos_of_a_deleted_list(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('DELETE FROM receipt');
        $receipts = self::getContainer()->get('test.receipts.repository');
        $photos = self::getContainer()->get('test.receipts.photo_storage');
        self::assertInstanceOf(ReceiptRepository::class, $receipts);
        self::assertInstanceOf(ReceiptPhotoStorage::class, $photos);
        $id = ReceiptId::fromString(self::RECEIPT);
        $receipts->save(Receipt::upload($id, ListId::fromString(self::LIST), SupermarketId::fromString('mercadona'), UserId::fromString('0192f3a4-7b1c-7d2e-8f30-000000000001'), PhotoType::JPEG, new \DateTimeImmutable()));
        $photos->store($id, 'photo');
        $handler = self::getContainer()->get(ShoppingListDeletedMessageHandler::class);
        self::assertInstanceOf(ShoppingListDeletedMessageHandler::class, $handler);

        $handler(new ShoppingListDeleted(self::LIST));

        self::assertNull($receipts->findById($id));
        self::assertNull($photos->read($id));
    }
}
