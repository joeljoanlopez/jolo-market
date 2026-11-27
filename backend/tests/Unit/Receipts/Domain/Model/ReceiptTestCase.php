<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Domain\Model;

use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Model\ReceiptLine;
use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\ReceiptLineId;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

abstract class ReceiptTestCase extends TestCase
{
    protected const string RECEIPT = '0193aaaa-0000-7000-8000-000000000001';
    protected const string LIST = '0193bbbb-0000-7000-8000-000000000001';
    protected const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    protected const string MILK = '0191bbbb-0000-7000-8000-000000000001';
    protected const string BREAD = '0191bbbb-0000-7000-8000-000000000003';

    protected static function uploaded(): Receipt
    {
        return Receipt::upload(
            ReceiptId::fromString(self::RECEIPT),
            ListId::fromString(self::LIST),
            SupermarketId::fromString('mercadona'),
            UserId::fromString(self::MARIA),
            PhotoType::JPEG,
            self::at('2026-11-14 18:00:00'),
        );
    }

    protected static function lineId(int $n): ReceiptLineId
    {
        return ReceiptLineId::fromString(\sprintf('0193cccc-0000-7000-8000-%012d', $n));
    }

    protected static function productLine(int $n, string $text, int $cents, ?LineQuantity $quantity = null): ReceiptLine
    {
        return ReceiptLine::product(self::lineId($n), LineText::fromString($text), $quantity ?? LineQuantity::units(1), Money::eur($cents));
    }

    protected static function discountLine(int $n, string $text, int $cents): ReceiptLine
    {
        return ReceiptLine::discount(self::lineId($n), LineText::fromString($text), Money::eur($cents));
    }

    protected static function at(string $utc): \DateTimeImmutable
    {
        return new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
    }
}
