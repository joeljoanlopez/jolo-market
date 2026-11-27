<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Application;

use App\Receipts\Application\LineLinker;
use App\Receipts\Application\UploadReceipt\UploadReceiptCommand;
use App\Receipts\Application\UploadReceipt\UploadReceiptHandler;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\Receipts\FakeListAccess;
use App\Tests\Support\Receipts\FakeReceiptReader;
use App\Tests\Support\Receipts\FixedReceiptSupermarkets;
use App\Tests\Support\Receipts\InMemoryProductLinkMemory;
use App\Tests\Support\Receipts\InMemoryReceiptPhotoStorage;
use App\Tests\Support\Receipts\InMemoryReceiptProductCatalog;
use App\Tests\Support\Receipts\InMemoryReceiptRepository;
use App\Tests\Support\Receipts\InMemoryReceiptUserDirectory;
use App\Tests\Support\Receipts\RecordingReceiptReadingQueue;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Handlers wired to in-memory fakes. Maria owns the list, Joan is a member and Pedro is an outsider.
 */
abstract class ReceiptHandlersTestCase extends TestCase
{
    protected const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    protected const string JOAN = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    protected const string PEDRO = '0192f3a4-7b1c-7d2e-8f30-000000000003';
    protected const string LIST = '0193bbbb-0000-7000-8000-000000000001';
    protected const string OTHER_LIST = '0193bbbb-0000-7000-8000-000000000002';
    protected const string UNKNOWN = '0193ffff-0000-7000-8000-000000000099';
    protected const string MILK = '0191bbbb-0000-7000-8000-000000000001';
    protected const string BREAD = '0191bbbb-0000-7000-8000-000000000002';
    protected const string ALDI_MILK = '0191bbbb-0000-7000-8000-000000000003';

    /** The smallest file with the JPEG signature. */
    protected const string JPEG = "\xFF\xD8\xFF\xE0jpeg";

    protected InMemoryReceiptRepository $receipts;
    protected InMemoryReceiptPhotoStorage $photos;
    protected InMemoryProductLinkMemory $memory;
    protected InMemoryReceiptProductCatalog $catalog;
    protected InMemoryReceiptUserDirectory $users;
    protected FixedReceiptSupermarkets $supermarkets;
    protected FakeListAccess $access;
    protected FakeReceiptReader $reader;
    protected RecordingReceiptReadingQueue $queue;
    protected SequentialIdGenerator $ids;
    protected FixedClock $clock;

    protected function setUp(): void
    {
        $this->receipts = new InMemoryReceiptRepository();
        $this->photos = new InMemoryReceiptPhotoStorage();
        $this->memory = new InMemoryProductLinkMemory();
        $this->catalog = new InMemoryReceiptProductCatalog();
        $this->catalog->add(self::MILK, 'mercadona', 'Leche entera', 'Hacendado');
        $this->catalog->add(self::BREAD, 'mercadona', 'Pan de molde', 'Hacendado', available: false);
        $this->catalog->add(self::ALDI_MILK, 'aldi', 'Leche entera', 'Milsani');
        $this->users = new InMemoryReceiptUserDirectory([self::MARIA => 'maria', self::JOAN => 'joan']);
        $this->supermarkets = new FixedReceiptSupermarkets(
            ['mercadona' => 'Mercadona', 'aldi' => 'Aldi', 'bonpreu' => 'Bonpreu i Esclat'],
            ['mercadona', 'aldi'],
        );
        $this->access = new FakeListAccess();
        $this->access->allow(self::LIST, self::MARIA, self::JOAN);
        $this->access->allow(self::OTHER_LIST, self::PEDRO);
        $this->reader = new FakeReceiptReader();
        $this->queue = new RecordingReceiptReadingQueue();
        $this->ids = new SequentialIdGenerator();
        $this->clock = FixedClock::at('2026-11-14 18:00:00');
    }

    protected function upload(string $actor = self::MARIA, string $supermarket = 'mercadona', string $photo = self::JPEG, string $listId = self::LIST): string
    {
        return ($this->uploadHandler())(new UploadReceiptCommand($actor, $listId, $supermarket, $photo));
    }

    protected function uploadHandler(): UploadReceiptHandler
    {
        return new UploadReceiptHandler($this->access, $this->supermarkets, $this->receipts, $this->photos, $this->queue, $this->ids, $this->clock);
    }

    protected function lineLinker(): LineLinker
    {
        return new LineLinker($this->memory, $this->catalog);
    }

    protected function receipt(string $id): Receipt
    {
        return $this->receipts->findById(ReceiptId::fromString($id)) ?? self::fail('The receipt was not saved.');
    }
}
