<?php

declare(strict_types=1);

namespace App\Tests\Functional\Receipts\Infrastructure\Api;

use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Receipts\Domain\ValueObject\ReceiptLineId;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;

final class ReceiptQueriesApiTest extends ReceiptsApiTestCase
{
    #[Test]
    public function a_member_gets_a_receipt_with_its_lines_and_difference(): void
    {
        $milk = $this->seedProduct(1, 'Leche entera', 125, brand: 'Hacendado');
        $this->actAs('joan');
        $id = $this->uploadedDraft();
        $this->edit($id, static function ($receipt) use ($milk): void {
            $receipt->changeDate(ReceiptDate::fromString('2026-11-14'), new \DateTimeImmutable('2026-11-20'));
            $receipt->changeTotal(Money::eur(1000));
            $receipt->addProductLine(self::lineId(1), LineText::fromString('LECHE ENT.'), LineQuantity::kilograms(1500), Money::eur(750));
            $receipt->linkProduct(self::lineId(1), ProductId::fromString($milk));
            $receipt->addDiscountLine(self::lineId(2), LineText::fromString('DTO'), Money::eur(50));
        });
        $this->actAs('maria');

        $this->send('GET', '/api/lists/'.self::LIST.'/receipts/'.$id);

        self::assertResponseIsSuccessful();
        $json = $this->json();
        self::assertSame($id, $json['id']);
        self::assertSame('draft', $json['status']);
        self::assertSame('mercadona', $json['supermarketId']);
        self::assertSame('Mercadona', $json['supermarketName']);
        self::assertSame('2026-11-14', $json['date']);
        self::assertSame(1000, $json['totalCents']);
        self::assertSame(['id' => $this->joan, 'username' => 'joan'], $json['uploader']);
        self::assertIsString($json['uploadedAt']);
        self::assertSame(-300, $json['differenceCents']);
        self::assertArrayNotHasKey('readBy', $json);
        self::assertSame([
            [
                'id' => self::lineId(1)->value(), 'kind' => 'product', 'text' => 'LECHE ENT.', 'unit' => 'kg', 'quantity' => 1500, 'amountCents' => 750,
                'linkedProduct' => ['id' => $milk, 'name' => 'Leche entera', 'brand' => 'Hacendado', 'available' => true],
                'suggestedProduct' => null,
            ],
            [
                'id' => self::lineId(2)->value(), 'kind' => 'discount', 'text' => 'DTO', 'unit' => null, 'quantity' => null, 'amountCents' => 50,
                'linkedProduct' => null, 'suggestedProduct' => null,
            ],
        ], $json['lines']);
    }

    #[Test]
    public function the_list_shows_pending_and_confirmed_receipts_apart(): void
    {
        $this->actAs('maria');
        $draft = $this->uploadedDraft();
        $confirmed = $this->confirmedReceipt('2026-11-10', 2599);
        $this->uploadPhoto();
        $reading = $this->json()['id'];

        $this->send('GET', '/api/lists/'.self::LIST.'/receipts');

        self::assertResponseIsSuccessful();
        $json = $this->json();
        self::assertIsArray($json['pending']);
        self::assertIsArray($json['confirmed']);
        self::assertSame([$reading, $draft], array_column($json['pending'], 'id'));
        self::assertSame(['reading', 'draft'], array_column($json['pending'], 'status'));
        self::assertSame([$confirmed], array_column($json['confirmed'], 'id'));
        $summary = $json['confirmed'][0];
        self::assertIsArray($summary);
        self::assertSame('2026-11-10', $summary['date']);
        self::assertSame(2599, $summary['totalCents']);
        self::assertSame('Mercadona', $summary['supermarketName']);
        self::assertSame(['id' => $this->maria, 'username' => 'maria'], $summary['uploader']);
    }

    #[Test]
    public function the_spending_counts_confirmed_receipts_per_month_and_supermarket(): void
    {
        $this->actAs('maria');
        $this->confirmedReceipt('2026-11-10', 1000);
        $this->confirmedReceipt('2026-11-12', 500, 'aldi');
        $this->confirmedReceipt('2026-10-31', 300);
        $this->uploadedDraft();

        $this->send('GET', '/api/lists/'.self::LIST.'/spending');

        self::assertResponseIsSuccessful();
        self::assertSame(['months' => [
            ['month' => '2026-11', 'totalCents' => 1500, 'supermarkets' => [
                ['supermarketId' => 'aldi', 'supermarketName' => 'Aldi', 'totalCents' => 500],
                ['supermarketId' => 'mercadona', 'supermarketName' => 'Mercadona', 'totalCents' => 1000],
            ]],
            ['month' => '2026-10', 'totalCents' => 300, 'supermarkets' => [
                ['supermarketId' => 'mercadona', 'supermarketName' => 'Mercadona', 'totalCents' => 300],
            ]],
        ]], $this->json());
    }

    #[Test]
    public function outsiders_get_404_for_receipts_spending_and_a_receipt(): void
    {
        $this->actAs('maria');
        $id = $this->uploadedDraft();
        $this->actAs('pedro');

        foreach (['/receipts', '/spending', '/receipts/'.$id] as $path) {
            $this->send('GET', '/api/lists/'.self::LIST.$path);
            $this->assertProblem(404);
        }
    }

    #[Test]
    public function an_unknown_receipt_gets_404(): void
    {
        $this->actAs('maria');

        $this->send('GET', '/api/lists/'.self::LIST.'/receipts/'.self::UNKNOWN);

        $this->assertProblem(404);
    }

    protected function confirmedReceipt(string $date, int $totalCents, string $supermarketId = 'mercadona'): string
    {
        $id = $this->uploadedDraft($supermarketId);
        $this->edit($id, static function ($receipt) use ($date, $totalCents): void {
            $receipt->changeDate(ReceiptDate::fromString($date), new \DateTimeImmutable('2026-11-20'));
            $receipt->changeTotal(Money::eur($totalCents));
            $receipt->confirm();
        });

        return $id;
    }

    /**
     * @param callable(\App\Receipts\Domain\Model\Receipt): void $change
     */
    private function edit(string $id, callable $change): void
    {
        $receipt = $this->storedReceipt($id) ?? self::fail('The receipt is not stored.');
        $change($receipt);
        $this->receipts()->save($receipt);
    }

    private static function lineId(int $n): ReceiptLineId
    {
        return ReceiptLineId::fromString(\sprintf('0193cccc-0000-7000-8000-%012d', $n));
    }
}
