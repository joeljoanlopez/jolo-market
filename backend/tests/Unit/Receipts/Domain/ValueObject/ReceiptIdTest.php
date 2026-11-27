<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Domain\ValueObject;

use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Shared\Domain\ValueObject\InvalidUuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReceiptIdTest extends TestCase
{
    #[Test]
    public function a_receipt_id_is_a_uuid(): void
    {
        self::assertSame('0193aaaa-0000-7000-8000-000000000001', ReceiptId::fromString('0193AAAA-0000-7000-8000-000000000001')->value());

        $this->expectException(InvalidUuid::class);
        ReceiptId::fromString('receipt-1');
    }
}
