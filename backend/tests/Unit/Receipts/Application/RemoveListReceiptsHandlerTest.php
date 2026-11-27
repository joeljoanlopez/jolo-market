<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Application;

use App\Receipts\Application\RemoveListReceipts\RemoveListReceiptsCommand;
use App\Receipts\Application\RemoveListReceipts\RemoveListReceiptsHandler;
use App\Receipts\Domain\ValueObject\ReceiptId;
use PHPUnit\Framework\Attributes\Test;

final class RemoveListReceiptsHandlerTest extends ReceiptHandlersTestCase
{
    #[Test]
    public function deleting_a_list_removes_its_receipts_and_their_photos_only(): void
    {
        $this->upload();
        $this->draft();
        $kept = $this->upload(self::PEDRO, listId: self::OTHER_LIST);

        (new RemoveListReceiptsHandler($this->receipts, $this->photos))(new RemoveListReceiptsCommand(self::LIST));

        self::assertSame(1, $this->receipts->count());
        self::assertSame(1, $this->photos->count());
        self::assertNotNull($this->receipts->findById(ReceiptId::fromString($kept)));
        self::assertNotNull($this->photos->read(ReceiptId::fromString($kept)));
    }

    #[Test]
    public function a_list_without_receipts_or_a_malformed_id_is_a_no_op(): void
    {
        $this->upload();

        (new RemoveListReceiptsHandler($this->receipts, $this->photos))(new RemoveListReceiptsCommand(self::UNKNOWN));
        (new RemoveListReceiptsHandler($this->receipts, $this->photos))(new RemoveListReceiptsCommand('list-1'));

        self::assertSame(1, $this->receipts->count());
    }
}
