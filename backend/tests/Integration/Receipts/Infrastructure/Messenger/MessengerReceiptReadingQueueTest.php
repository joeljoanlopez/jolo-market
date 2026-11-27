<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\Messenger;

use App\Receipts\Application\ReadReceipt\ReadReceiptCommand;
use App\Receipts\Application\ReceiptReadingQueue;
use App\Receipts\Domain\ValueObject\ReceiptId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use PHPUnit\Framework\Attributes\Test;

final class MessengerReceiptReadingQueueTest extends KernelTestCase
{
    #[Test]
    public function asking_to_read_a_receipt_queues_it_for_the_worker(): void
    {
        self::bootKernel();
        $queue = self::getContainer()->get('test.receipts.reading_queue');
        self::assertInstanceOf(ReceiptReadingQueue::class, $queue);

        $queue->requestReading(ReceiptId::fromString('0193aaaa-0000-7000-8000-000000000001'));

        $transport = self::getContainer()->get('messenger.transport.receipts');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $sent = $transport->getSent();
        self::assertCount(1, $sent);
        self::assertEquals(new ReadReceiptCommand('0193aaaa-0000-7000-8000-000000000001'), $sent[0]->getMessage());
    }
}
