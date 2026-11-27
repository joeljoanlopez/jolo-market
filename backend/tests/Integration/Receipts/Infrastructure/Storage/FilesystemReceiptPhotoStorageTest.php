<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\Storage;

use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Infrastructure\Storage\FilesystemReceiptPhotoStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilesystemReceiptPhotoStorageTest extends TestCase
{
    private const string RECEIPT = '0193aaaa-0000-7000-8000-000000000001';

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/receipt-photos-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    #[Test]
    public function it_stores_and_reads_a_photo_named_by_the_receipt_id_only(): void
    {
        $storage = new FilesystemReceiptPhotoStorage($this->directory);

        $storage->store(ReceiptId::fromString(self::RECEIPT), "\xFF\xD8\xFFphoto");

        self::assertSame("\xFF\xD8\xFFphoto", $storage->read(ReceiptId::fromString(self::RECEIPT)));
        self::assertSame([$this->directory.'/'.self::RECEIPT], glob($this->directory.'/*'));
    }

    #[Test]
    public function storing_again_replaces_the_photo(): void
    {
        $storage = new FilesystemReceiptPhotoStorage($this->directory);
        $storage->store(ReceiptId::fromString(self::RECEIPT), 'first');

        $storage->store(ReceiptId::fromString(self::RECEIPT), 'second');

        self::assertSame('second', $storage->read(ReceiptId::fromString(self::RECEIPT)));
    }

    #[Test]
    public function a_missing_photo_reads_as_null(): void
    {
        self::assertNull((new FilesystemReceiptPhotoStorage($this->directory))->read(ReceiptId::fromString(self::RECEIPT)));
    }

    #[Test]
    public function deleting_removes_the_photo_and_is_idempotent(): void
    {
        $storage = new FilesystemReceiptPhotoStorage($this->directory);
        $storage->store(ReceiptId::fromString(self::RECEIPT), 'photo');

        $storage->delete(ReceiptId::fromString(self::RECEIPT));
        $storage->delete(ReceiptId::fromString(self::RECEIPT));

        self::assertNull($storage->read(ReceiptId::fromString(self::RECEIPT)));
        self::assertSame([], glob($this->directory.'/*'));
    }
}
