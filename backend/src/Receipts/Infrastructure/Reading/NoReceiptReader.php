<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Reading;

use App\Receipts\Application\ReadReceipt\ReadReceiptData;
use App\Receipts\Application\ReadReceipt\ReceiptReader;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\SupermarketId;
use Psr\Log\LoggerInterface;

/**
 * Until the AI and OCR readers exist (E10.21, PD-127), every reading fails, so each upload becomes
 * an empty draft read by none that members fill in by hand (PD-65, PD-114).
 */
final readonly class NoReceiptReader implements ReceiptReader
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function read(string $photo, PhotoType $photoType, SupermarketId $supermarketId): ?ReadReceiptData
    {
        $this->logger->info('Receipt not read: no receipt reader is available yet.', ['supermarket' => $supermarketId->value(), 'readBy' => 'none']);

        return null;
    }
}
