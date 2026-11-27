<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Application;

use App\Receipts\Application\GetReceiptPhoto\GetReceiptPhotoHandler;
use App\Receipts\Application\GetReceiptPhoto\GetReceiptPhotoQuery;
use App\Receipts\Application\ReceiptListNotFound;
use App\Receipts\Application\ReceiptNotFound;
use App\Receipts\Domain\ValueObject\ReceiptId;
use PHPUnit\Framework\Attributes\Test;

final class GetReceiptPhotoHandlerTest extends ReceiptHandlersTestCase
{
    #[Test]
    public function a_member_gets_the_photo_with_its_media_type(): void
    {
        $receiptId = $this->upload();

        $photo = ($this->handler())(new GetReceiptPhotoQuery(self::JOAN, self::LIST, $receiptId));

        self::assertSame(self::JPEG, $photo->contents);
        self::assertSame('image/jpeg', $photo->mediaType);
    }

    #[Test]
    public function an_outsider_gets_not_found(): void
    {
        $receiptId = $this->upload();

        $this->expectException(ReceiptListNotFound::class);

        ($this->handler())(new GetReceiptPhotoQuery(self::PEDRO, self::LIST, $receiptId));
    }

    #[Test]
    public function a_missing_photo_is_not_found(): void
    {
        $receiptId = $this->upload();
        $this->photos->delete(ReceiptId::fromString($receiptId));

        $this->expectException(ReceiptNotFound::class);

        ($this->handler())(new GetReceiptPhotoQuery(self::MARIA, self::LIST, $receiptId));
    }

    private function handler(): GetReceiptPhotoHandler
    {
        return new GetReceiptPhotoHandler($this->updater(), $this->photos);
    }
}
