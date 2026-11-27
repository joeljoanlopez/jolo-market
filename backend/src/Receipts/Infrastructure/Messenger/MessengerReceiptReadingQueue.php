<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Messenger;

use App\Receipts\Application\ReadReceipt\ReadReceiptCommand;
use App\Receipts\Application\ReceiptReadingQueue;
use App\Receipts\Domain\ValueObject\ReceiptId;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues the reading on the receipts transport (config/packages/messenger.yaml); the worker runs it.
 */
final readonly class MessengerReceiptReadingQueue implements ReceiptReadingQueue
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function requestReading(ReceiptId $receiptId): void
    {
        $this->bus->dispatch(new ReadReceiptCommand($receiptId->value()));
    }
}
