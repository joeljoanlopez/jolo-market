<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\Persistence\Dbal;

use App\Receipts\Domain\Model\ReadResult;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Receipts\Infrastructure\Persistence\Dbal\DbalReceiptRepository;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class ReceiptsDbalTestCase extends KernelTestCase
{
    protected const string LIST = '0193bbbb-0000-7000-8000-000000000001';
    protected const string OTHER_LIST = '0193bbbb-0000-7000-8000-000000000002';
    protected const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    protected const string MILK = '0191bbbb-0000-7000-8000-000000000001';
    protected const string BREAD = '0191bbbb-0000-7000-8000-000000000002';

    protected Connection $connection;
    protected DbalReceiptRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM receipt');
        $this->connection->executeStatement('DELETE FROM receipt_product_link');
        $this->repository = new DbalReceiptRepository($this->connection);
    }

    protected function load(int $n): Receipt
    {
        return $this->repository->findById(ReceiptId::fromString(self::receiptId($n))) ?? self::fail('The receipt was not saved.');
    }

    protected function countRows(string $table): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    protected static function receiptId(int $n): string
    {
        return \sprintf('0193aaaa-0000-7000-8000-%012d', $n);
    }

    protected static function uploaded(int $n, string $listId = self::LIST, string $supermarketId = 'mercadona'): Receipt
    {
        return Receipt::upload(
            ReceiptId::fromString(self::receiptId($n)),
            ListId::fromString($listId),
            SupermarketId::fromString($supermarketId),
            UserId::fromString(self::MARIA),
            PhotoType::PNG,
            new \DateTimeImmutable('2026-11-14 18:00:00.123456', new \DateTimeZone('UTC')),
        );
    }

    /**
     * Saved, read as nothing, dated, totalled and confirmed, as a member would.
     */
    protected function confirmed(int $n, string $date, int $totalCents, string $listId = self::LIST, string $supermarketId = 'mercadona'): void
    {
        $this->repository->save(self::uploaded($n, $listId, $supermarketId));
        $receipt = $this->load($n);
        $receipt->completeReading(ReadResult::failed(), new \DateTimeImmutable('2026-12-31'));
        $receipt->changeDate(ReceiptDate::fromString($date), new \DateTimeImmutable('2026-12-31'));
        $receipt->changeTotal(Money::eur($totalCents));
        $receipt->confirm();
        $this->repository->save($receipt);
    }
}
