<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Messenger;

use App\Receipts\Application\ReadReceipt\ReadReceiptCommand;
use App\Receipts\Application\ReadReceipt\ReadReceiptHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Run by the worker (messenger:consume receipts).
 */
#[AsMessageHandler]
final readonly class ReadReceiptMessageHandler
{
    public function __construct(private ReadReceiptHandler $readReceipt)
    {
    }

    public function __invoke(ReadReceiptCommand $command): void
    {
        ($this->readReceipt)($command);
    }
}
