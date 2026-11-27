<?php

declare(strict_types=1);

namespace App\Tests\Functional\Receipts\Infrastructure\Api;

use App\Receipts\Domain\ValueObject\ReceiptStatus;
use PHPUnit\Framework\Attributes\Test;

final class EditReceiptApiTest extends ReceiptsApiTestCase
{
    #[Test]
    public function a_member_reviews_and_confirms_a_receipt(): void
    {
        $bread = $this->seedProduct(1, 'Pan de molde', 135, brand: 'Hacendado');
        $milk = $this->seedProduct(2, 'Leche entera', 125, brand: 'Hacendado');
        $this->actAs('maria');
        $id = $this->uploadedDraft();
        $this->actAs('joan');
        $base = '/api/lists/'.self::LIST.'/receipts/'.$id;

        $this->send('PATCH', $base, ['date' => '2026-11-14', 'totalCents' => 400]);
        self::assertResponseStatusCodeSame(204);

        $this->send('POST', $base.'/lines', ['kind' => 'product', 'text' => 'PAN MOLDE', 'unit' => 'units', 'quantity' => 2, 'amountCents' => 270]);
        self::assertResponseStatusCodeSame(201);
        $breadLine = $this->json()['id'];
        self::assertIsString($breadLine);
        $this->send('POST', $base.'/lines', ['kind' => 'product', 'text' => 'LECHE', 'unit' => 'units', 'quantity' => 1, 'amountCents' => 125]);
        $milkLine = $this->json()['id'];
        self::assertIsString($milkLine);
        $this->send('POST', $base.'/lines', ['kind' => 'discount', 'text' => 'DTO', 'amountCents' => 10]);
        $discountLine = $this->json()['id'];
        self::assertIsString($discountLine);

        $this->send('PATCH', $base.'/lines/'.$breadLine, ['quantity' => 3, 'unit' => 'units', 'amountCents' => 405]);
        self::assertResponseStatusCodeSame(204);
        $this->send('PUT', $base.'/lines/'.$breadLine.'/product', ['productId' => $bread]);
        self::assertResponseStatusCodeSame(204);
        $this->send('PUT', $base.'/lines/'.$milkLine.'/product', ['productId' => $milk]);
        $this->send('DELETE', $base.'/lines/'.$milkLine.'/product');
        self::assertResponseStatusCodeSame(204);
        $this->send('DELETE', $base.'/lines/'.$discountLine);
        self::assertResponseStatusCodeSame(204);

        $this->send('POST', $base.'/confirm');
        self::assertResponseStatusCodeSame(204);

        $this->send('GET', $base);
        $json = $this->json();
        self::assertSame('confirmed', $json['status']);
        self::assertSame('2026-11-14', $json['date']);
        self::assertSame(400, $json['totalCents']);
        self::assertSame(405 + 125 - 400, $json['differenceCents']);
        self::assertIsArray($json['lines']);
        self::assertCount(2, $json['lines']);
        self::assertIsArray($json['lines'][0]);
        self::assertSame(3, $json['lines'][0]['quantity']);
        self::assertIsArray($json['lines'][0]['linkedProduct']);
        self::assertSame($bread, $json['lines'][0]['linkedProduct']['id']);
        self::assertIsArray($json['lines'][1]);
        self::assertNull($json['lines'][1]['linkedProduct']);
    }

    #[Test]
    public function a_confirmed_text_is_linked_by_itself_on_the_next_receipt(): void
    {
        $bread = $this->seedProduct(1, 'Pan de molde', 135);
        $this->actAs('maria');
        $first = $this->uploadedDraft();
        $base = '/api/lists/'.self::LIST.'/receipts/'.$first;
        $this->send('PATCH', $base, ['date' => '2026-11-14', 'totalCents' => 135]);
        $this->send('POST', $base.'/lines', ['kind' => 'product', 'text' => 'PAN MOLDE', 'unit' => 'units', 'quantity' => 1, 'amountCents' => 135]);
        $line = $this->json()['id'];
        self::assertIsString($line);
        $this->send('PUT', $base.'/lines/'.$line.'/product', ['productId' => $bread]);
        $this->send('POST', $base.'/confirm');

        $second = $this->uploadedDraft();
        $this->send('POST', '/api/lists/'.self::LIST.'/receipts/'.$second.'/lines', ['kind' => 'product', 'text' => 'pan  molde', 'unit' => 'units', 'quantity' => 1, 'amountCents' => 135]);
        $this->send('GET', '/api/lists/'.self::LIST.'/receipts/'.$second);

        $json = $this->json();
        self::assertIsArray($json['lines']);
        self::assertIsArray($json['lines'][0]);
        self::assertIsArray($json['lines'][0]['linkedProduct']);
        self::assertSame($bread, $json['lines'][0]['linkedProduct']['id']);
    }

    #[Test]
    public function a_member_deletes_a_receipt_with_its_photo(): void
    {
        $this->actAs('maria');
        $this->uploadPhoto();
        $id = $this->json()['id'];
        self::assertIsString($id);
        $this->actAs('joan');

        $this->send('DELETE', '/api/lists/'.self::LIST.'/receipts/'.$id);

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->storedReceipt($id));
        self::assertNull($this->storedPhoto($id));
    }

    #[Test]
    public function editing_or_confirming_a_receipt_still_being_read_gets_409(): void
    {
        $this->actAs('maria');
        $this->uploadPhoto();
        $id = $this->json()['id'];
        self::assertIsString($id);

        $this->send('PATCH', '/api/lists/'.self::LIST.'/receipts/'.$id, ['totalCents' => 100]);
        $this->assertProblem(409);
        $this->send('POST', '/api/lists/'.self::LIST.'/receipts/'.$id.'/confirm');
        $this->assertProblem(409);
        self::assertSame(ReceiptStatus::READING, $this->storedReceipt($id)?->status());
    }

    #[Test]
    public function invalid_data_gets_422(): void
    {
        $this->seedProduct(1, 'Leche', 100, supermarket: 'aldi');
        $this->actAs('maria');
        $id = $this->uploadedDraft();
        $base = '/api/lists/'.self::LIST.'/receipts/'.$id;
        $this->send('POST', $base.'/lines', ['kind' => 'product', 'text' => 'LECHE', 'unit' => 'units', 'quantity' => 1, 'amountCents' => 100]);
        $line = $this->json()['id'];
        self::assertIsString($line);

        $invalid = [
            ['PATCH', $base, ['date' => '2099-01-01']],
            ['PATCH', $base, ['totalCents' => '100']],
            ['PATCH', $base, ['totalCents' => 0]],
            ['POST', $base.'/lines', ['kind' => 'product', 'text' => 'PAN', 'amountCents' => 100]],
            ['POST', $base.'/lines', ['kind' => 'tax', 'text' => 'IVA', 'amountCents' => 100]],
            ['POST', $base.'/lines', ['kind' => 'product', 'text' => str_repeat('a', 121), 'unit' => 'units', 'quantity' => 1, 'amountCents' => 100]],
            ['PATCH', $base.'/lines/'.$line, ['quantity' => 0, 'unit' => 'units']],
            ['PUT', $base.'/lines/'.$line.'/product', ['productId' => '0191bbbb-0000-7000-8000-000000000001']],
            ['PUT', $base.'/lines/'.$line.'/product', []],
            ['POST', $base.'/confirm', null],
        ];
        foreach ($invalid as [$method, $uri, $body]) {
            $this->send($method, $uri, $body);
            $this->assertProblem(422);
        }
    }

    #[Test]
    public function outsiders_and_unknown_receipts_or_lines_get_404(): void
    {
        $this->actAs('maria');
        $id = $this->uploadedDraft();
        $base = '/api/lists/'.self::LIST.'/receipts/'.$id;
        $this->send('POST', $base.'/lines', ['kind' => 'discount', 'text' => 'DTO', 'amountCents' => 10]);
        $line = $this->json()['id'];
        self::assertIsString($line);

        $this->send('PATCH', $base.'/lines/'.self::UNKNOWN, ['text' => 'PAN']);
        $this->assertProblem(404);
        $this->send('DELETE', '/api/lists/'.self::LIST.'/receipts/'.self::UNKNOWN);
        $this->assertProblem(404);

        $this->actAs('pedro');
        $requests = [
            ['PATCH', $base, ['totalCents' => 100]],
            ['POST', $base.'/lines', ['kind' => 'discount', 'text' => 'DTO', 'amountCents' => 10]],
            ['PATCH', $base.'/lines/'.$line, ['text' => 'X']],
            ['DELETE', $base.'/lines/'.$line, null],
            ['PUT', $base.'/lines/'.$line.'/product', ['productId' => self::UNKNOWN]],
            ['DELETE', $base.'/lines/'.$line.'/product', null],
            ['POST', $base.'/confirm', null],
            ['DELETE', $base, null],
        ];
        foreach ($requests as [$method, $uri, $body]) {
            $this->send($method, $uri, $body);
            $this->assertProblem(404);
        }
        self::assertNotNull($this->storedReceipt($id));
    }
}
