<?php

declare(strict_types=1);

namespace App\Tests\Functional\Receipts\Infrastructure\Api;

use App\Receipts\Application\ReadReceipt\ReadReceiptCommand;
use App\Receipts\Application\ReadReceipt\ReadReceiptHandler;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Port\ReceiptPhotoStorage;
use App\Receipts\Domain\Port\ReceiptRepository;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Tests\Functional\ShoppingList\Infrastructure\Api\ShoppingListApiTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Maria's shared list, which Joan joined; Pedro is an outsider. Uploads go through the real
 * use case; the worker is simulated by running the queued readings (the reader always fails, PD-127).
 */
abstract class ReceiptsApiTestCase extends ShoppingListApiTestCase
{
    protected const string JPEG = "\xFF\xD8\xFF\xE0jpeg-bytes";

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('DELETE FROM receipt');
        $connection->executeStatement('DELETE FROM receipt_product_link');
        $this->storeSharedList();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    /**
     * @param array<string, string> $server
     */
    protected function uploadPhoto(string $contents = self::JPEG, string $supermarketId = 'mercadona', string $listId = self::LIST, string $fileName = 'ticket.jpg', array $server = ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']): void
    {
        $path = tempnam(sys_get_temp_dir(), 'receipt-');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $this->files[] = $path;

        $this->client->request(
            'POST',
            '/api/lists/'.$listId.'/receipts',
            parameters: ['supermarketId' => $supermarketId],
            files: ['photo' => new UploadedFile($path, $fileName, 'image/jpeg', test: true)],
            server: $server,
        );
    }

    /**
     * Uploads a photo as the logged-in user and lets the worker read it into an empty draft.
     */
    protected function uploadedDraft(string $supermarketId = 'mercadona'): string
    {
        $this->uploadPhoto(supermarketId: $supermarketId);
        self::assertResponseStatusCodeSame(202);
        $id = $this->json()['id'];
        self::assertIsString($id);
        $this->runWorker();

        return $id;
    }

    protected function runWorker(): void
    {
        $transport = self::getContainer()->get('messenger.transport.receipts');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $handler = self::getContainer()->get(ReadReceiptHandler::class);
        self::assertInstanceOf(ReadReceiptHandler::class, $handler);
        foreach ($transport->get() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof ReadReceiptCommand) {
                $handler($message);
            }
            $transport->ack($envelope);
        }
    }

    protected function storedReceipt(string $id): ?Receipt
    {
        return $this->receipts()->findById(ReceiptId::fromString($id));
    }

    protected function storedPhoto(string $id): ?string
    {
        $photos = self::getContainer()->get('test.receipts.photo_storage');
        self::assertInstanceOf(ReceiptPhotoStorage::class, $photos);

        return $photos->read(ReceiptId::fromString($id));
    }

    protected function receipts(): ReceiptRepository
    {
        $receipts = self::getContainer()->get('test.receipts.repository');
        self::assertInstanceOf(ReceiptRepository::class, $receipts);

        return $receipts;
    }
}
