<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Application;

use App\Receipts\Application\ReceiptListNotFound;
use App\Receipts\Application\UploadReceipt\InvalidReceiptPhoto;
use App\Receipts\Application\UploadReceipt\SupermarketNotEnabled;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\ReceiptStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class UploadReceiptHandlerTest extends ReceiptHandlersTestCase
{
    #[Test]
    public function a_member_uploads_a_photo_that_is_saved_as_reading_and_queued(): void
    {
        $receiptId = $this->upload(self::JOAN);

        self::assertSame('00000000-0000-4000-8000-000000000001', $receiptId);
        $receipt = $this->receipt($receiptId);
        self::assertSame(ReceiptStatus::READING, $receipt->status());
        self::assertSame(self::LIST, $receipt->listId()->value());
        self::assertSame('mercadona', $receipt->supermarketId()->value());
        self::assertSame(self::JOAN, $receipt->uploaderId()->value());
        self::assertSame(PhotoType::JPEG, $receipt->photoType());
        self::assertEquals($this->clock->now(), $receipt->uploadedAt());
        self::assertSame(self::JPEG, $this->photos->read(ReceiptId::fromString($receiptId)));
        self::assertSame([$receiptId], $this->queue->requested());
    }

    /**
     * @return iterable<string, array{string, PhotoType}>
     */
    public static function photos(): iterable
    {
        yield 'JPEG' => ["\xFF\xD8\xFF\xDB...", PhotoType::JPEG];
        yield 'PNG' => ["\x89PNG\r\n\x1A\n...", PhotoType::PNG];
        yield 'WebP' => ['RIFF'."\x10\x00\x00\x00".'WEBPVP8 ', PhotoType::WEBP];
    }

    #[Test]
    #[DataProvider('photos')]
    public function the_photo_type_comes_from_its_contents(string $photo, PhotoType $type): void
    {
        self::assertSame($type, $this->receipt($this->upload(photo: $photo))->photoType());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notPhotos(): iterable
    {
        yield 'PDF' => ['%PDF-1.7'];
        yield 'GIF' => ['GIF89a...'];
        yield 'RIFF that is not WebP' => ['RIFF'."\x10\x00\x00\x00".'WAVEfmt '];
        yield 'empty' => [''];
    }

    #[Test]
    #[DataProvider('notPhotos')]
    public function a_file_that_is_not_jpeg_png_or_webp_is_rejected(string $photo): void
    {
        $this->expectException(InvalidReceiptPhoto::class);

        $this->upload(photo: $photo);
    }

    #[Test]
    public function a_photo_of_10_mb_is_accepted_and_a_bigger_one_rejected(): void
    {
        $tenMb = self::JPEG.str_repeat('x', 10 * 1024 * 1024 - \strlen(self::JPEG));
        $this->upload(photo: $tenMb);

        $this->expectException(InvalidReceiptPhoto::class);
        $this->upload(photo: $tenMb.'x');
    }

    #[Test]
    public function a_supermarket_that_is_not_enabled_is_rejected(): void
    {
        foreach (['bonpreu', 'unknown', 'Not a slug'] as $supermarket) {
            try {
                $this->upload(supermarket: $supermarket);
                self::fail('Expected a SupermarketNotEnabled.');
            } catch (SupermarketNotEnabled) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertSame(0, $this->receipts->count());
    }

    #[Test]
    public function an_outsider_is_told_the_list_does_not_exist_before_anything_else(): void
    {
        try {
            $this->upload(self::PEDRO, supermarket: 'bonpreu', photo: '%PDF');
            self::fail('Expected a ReceiptListNotFound.');
        } catch (ReceiptListNotFound) {
            self::assertSame(0, $this->receipts->count());
            self::assertSame(0, $this->photos->count());
            self::assertSame([], $this->queue->requested());
        }
    }

    #[Test]
    public function a_malformed_list_id_does_not_exist(): void
    {
        $this->expectException(ReceiptListNotFound::class);

        $this->upload(listId: 'list-1');
    }
}
