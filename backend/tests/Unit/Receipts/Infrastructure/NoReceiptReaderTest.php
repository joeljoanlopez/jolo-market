<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Infrastructure;

use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Receipts\Infrastructure\Reading\NoReceiptReader;
use App\Tests\Support\InMemoryLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NoReceiptReaderTest extends TestCase
{
    #[Test]
    public function every_reading_fails_and_is_logged_without_the_photo(): void
    {
        $logger = new InMemoryLogger();

        self::assertNull((new NoReceiptReader($logger))->read("\xFF\xD8\xFFsecret", PhotoType::JPEG, SupermarketId::fromString('mercadona')));
        self::assertStringNotContainsString('secret', serialize($logger->records));
        self::assertNotSame([], $logger->records);
    }
}
