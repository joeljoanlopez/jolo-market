<?php

declare(strict_types=1);

namespace App\Receipts\Application\UploadReceipt;

use App\Receipts\Application\ListAccess;
use App\Receipts\Application\ReceiptIds;
use App\Receipts\Application\ReceiptReadingQueue;
use App\Receipts\Application\ReceiptSupermarkets;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Port\ReceiptPhotoStorage;
use App\Receipts\Domain\Port\ReceiptRepository;
use App\Receipts\Domain\ValueObject\InvalidSupermarketId;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\UserId;

/**
 * PD-63 to PD-65, PD-68: a member uploads the photo of a receipt of an enabled supermarket. It is
 * stored, the receipt is saved as reading and the worker is asked to read it, so this answers at once.
 */
final readonly class UploadReceiptHandler
{
    public const int MAX_PHOTO_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private ListAccess $access,
        private ReceiptSupermarkets $supermarkets,
        private ReceiptRepository $receipts,
        private ReceiptPhotoStorage $photos,
        private ReceiptReadingQueue $queue,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    /**
     * @return string the new receipt id
     */
    public function __invoke(UploadReceiptCommand $command): string
    {
        $listId = ReceiptIds::list($command->listId);
        $actorId = UserId::fromString($command->actorId);
        $this->access->assertCanAccess($actorId, $listId);

        $supermarketId = $this->enabledSupermarket($command->supermarketId);
        if (\strlen($command->photo) > self::MAX_PHOTO_BYTES) {
            throw InvalidReceiptPhoto::tooBig(self::MAX_PHOTO_BYTES);
        }
        $photoType = PhotoType::detect($command->photo) ?? throw InvalidReceiptPhoto::notAnImage();

        $receipt = Receipt::upload(ReceiptId::fromString($this->ids->generate()->value()), $listId, $supermarketId, $actorId, $photoType, $this->clock->now());
        $this->photos->store($receipt->id(), $command->photo);
        $this->receipts->save($receipt);
        $this->queue->requestReading($receipt->id());

        return $receipt->id()->value();
    }

    private function enabledSupermarket(string $supermarketId): SupermarketId
    {
        try {
            $id = SupermarketId::fromString($supermarketId);
        } catch (InvalidSupermarketId) {
            throw SupermarketNotEnabled::withId($supermarketId);
        }
        if (!$this->supermarkets->isEnabled($id)) {
            throw SupermarketNotEnabled::withId($supermarketId);
        }

        return $id;
    }
}
