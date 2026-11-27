<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Api;

use App\Receipts\Application\GetListReceipts\ListReceiptsView;
use App\Receipts\Application\GetListReceipts\ReceiptSummary;
use App\Receipts\Application\GetListSpending\MonthlySpending;
use App\Receipts\Application\GetListSpending\SupermarketSpending;
use App\Receipts\Application\GetReceipt\ReceiptLineProductView;
use App\Receipts\Application\GetReceipt\ReceiptLineView;
use App\Receipts\Application\GetReceipt\ReceiptUserView;
use App\Receipts\Application\GetReceipt\ReceiptView;

/**
 * The JSON of the receipt endpoints (docs/architecture.md, HTTP API).
 */
final class ReceiptJson
{
    /**
     * @return array<string, mixed>
     */
    public static function receipt(ReceiptView $receipt): array
    {
        return [
            'id' => $receipt->id,
            'status' => $receipt->status,
            'supermarketId' => $receipt->supermarketId,
            'supermarketName' => $receipt->supermarketName,
            'date' => $receipt->date,
            'totalCents' => $receipt->totalCents,
            'uploader' => self::user($receipt->uploader),
            'uploadedAt' => $receipt->uploadedAt,
            'differenceCents' => $receipt->differenceCents,
            'lines' => array_map(self::line(...), $receipt->lines),
        ];
    }

    /**
     * @return array{pending: list<array<string, mixed>>, confirmed: list<array<string, mixed>>}
     */
    public static function list(ListReceiptsView $receipts): array
    {
        return [
            'pending' => array_map(self::summary(...), $receipts->pending),
            'confirmed' => array_map(self::summary(...), $receipts->confirmed),
        ];
    }

    /**
     * @param list<MonthlySpending> $months
     *
     * @return array{months: list<array<string, mixed>>}
     */
    public static function spending(array $months): array
    {
        return ['months' => array_map(static fn (MonthlySpending $month): array => [
            'month' => $month->month,
            'totalCents' => $month->totalCents,
            'supermarkets' => array_map(static fn (SupermarketSpending $spending): array => [
                'supermarketId' => $spending->supermarketId,
                'supermarketName' => $spending->supermarketName,
                'totalCents' => $spending->totalCents,
            ], $month->supermarkets),
        ], $months)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(ReceiptSummary $receipt): array
    {
        return [
            'id' => $receipt->id,
            'status' => $receipt->status,
            'supermarketId' => $receipt->supermarketId,
            'supermarketName' => $receipt->supermarketName,
            'date' => $receipt->date,
            'totalCents' => $receipt->totalCents,
            'uploader' => self::user($receipt->uploader),
            'uploadedAt' => $receipt->uploadedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function line(ReceiptLineView $line): array
    {
        return [
            'id' => $line->id,
            'kind' => $line->kind,
            'text' => $line->text,
            'unit' => $line->unit,
            'quantity' => $line->quantity,
            'amountCents' => $line->amountCents,
            'linkedProduct' => self::product($line->linkedProduct),
            'suggestedProduct' => self::product($line->suggestedProduct),
        ];
    }

    /**
     * @return array{id: string, name: string|null, brand: string|null, available: bool}|null
     */
    private static function product(?ReceiptLineProductView $product): ?array
    {
        return null === $product ? null : ['id' => $product->productId, 'name' => $product->name, 'brand' => $product->brand, 'available' => $product->available];
    }

    /**
     * @return array{id: string, username: string}
     */
    private static function user(ReceiptUserView $user): array
    {
        return ['id' => $user->id, 'username' => $user->username];
    }
}
