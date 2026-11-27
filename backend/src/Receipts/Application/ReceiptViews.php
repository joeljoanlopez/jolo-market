<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Receipts\Application\GetListReceipts\ReceiptSummary;
use App\Receipts\Application\GetReceipt\ReceiptUserView;
use App\Receipts\Domain\Model\Receipt;

/**
 * What the receipt queries share.
 */
final class ReceiptViews
{
    public const string DATE_TIME_FORMAT = \DATE_ATOM;

    /**
     * @param array<string, string> $usernames        by user id
     * @param array<string, string> $supermarketNames by supermarket id
     */
    public static function summary(Receipt $receipt, array $usernames, array $supermarketNames): ReceiptSummary
    {
        return new ReceiptSummary(
            $receipt->id()->value(),
            $receipt->status()->value,
            $receipt->supermarketId()->value(),
            self::supermarketName($receipt, $supermarketNames),
            $receipt->date()?->value(),
            $receipt->total()?->cents(),
            self::uploader($receipt, $usernames),
            self::uploadedAt($receipt),
        );
    }

    /**
     * @param array<string, string> $usernames by user id
     */
    public static function uploader(Receipt $receipt, array $usernames): ReceiptUserView
    {
        // The uploader may have left the list; the receipt stays and still names them (PD-63).
        return new ReceiptUserView($receipt->uploaderId()->value(), $usernames[$receipt->uploaderId()->value()] ?? '');
    }

    /**
     * @param array<string, string> $supermarketNames by supermarket id
     */
    public static function supermarketName(Receipt $receipt, array $supermarketNames): string
    {
        return $supermarketNames[$receipt->supermarketId()->value()] ?? $receipt->supermarketId()->value();
    }

    public static function uploadedAt(Receipt $receipt): string
    {
        return $receipt->uploadedAt()->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_TIME_FORMAT);
    }
}
