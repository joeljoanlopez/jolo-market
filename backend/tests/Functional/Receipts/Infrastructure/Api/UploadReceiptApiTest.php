<?php

declare(strict_types=1);

namespace App\Tests\Functional\Receipts\Infrastructure\Api;

use App\Receipts\Domain\ValueObject\ReceiptStatus;
use PHPUnit\Framework\Attributes\Test;

final class UploadReceiptApiTest extends ReceiptsApiTestCase
{
    #[Test]
    public function a_member_uploads_a_photo_and_gets_the_receipt_id_at_once(): void
    {
        $this->actAs('joan');

        $this->uploadPhoto();

        self::assertResponseStatusCodeSame(202);
        $id = $this->json()['id'];
        self::assertIsString($id);
        self::assertSame(ReceiptStatus::READING, $this->storedReceipt($id)?->status());
        self::assertSame(self::JPEG, $this->storedPhoto($id));
    }

    #[Test]
    public function the_worker_turns_it_into_an_empty_draft_while_no_reader_exists(): void
    {
        $this->actAs('maria');

        $id = $this->uploadedDraft();

        self::assertSame(ReceiptStatus::DRAFT, $this->storedReceipt($id)?->status());
    }

    #[Test]
    public function an_outsider_gets_404(): void
    {
        $this->actAs('pedro');

        $this->uploadPhoto();

        $this->assertProblem(404);
    }

    #[Test]
    public function a_file_that_is_not_jpeg_png_or_webp_gets_422_whatever_its_name(): void
    {
        $this->actAs('maria');

        $this->uploadPhoto('%PDF-1.7', fileName: 'ticket.jpg');

        $this->assertProblem(422);
    }

    #[Test]
    public function a_photo_over_10_mb_gets_422(): void
    {
        $this->actAs('maria');

        $this->uploadPhoto(self::JPEG.str_repeat('x', 10 * 1024 * 1024));

        $this->assertProblem(422);
    }

    #[Test]
    public function a_supermarket_that_is_not_enabled_gets_422(): void
    {
        $this->actAs('maria');

        $this->uploadPhoto(supermarketId: 'bonpreu');

        $this->assertProblem(422);
    }

    #[Test]
    public function a_missing_photo_or_supermarket_gets_422(): void
    {
        $this->actAs('maria');

        $this->client->request('POST', '/api/lists/'.self::LIST.'/receipts', parameters: ['supermarketId' => 'mercadona'], server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $this->assertProblem(422);

        $this->uploadPhoto(supermarketId: '');
        $this->assertProblem(422);
    }

    #[Test]
    public function an_upload_without_the_csrf_header_gets_403(): void
    {
        $this->actAs('maria');

        $this->uploadPhoto(server: []);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function an_anonymous_upload_gets_401(): void
    {
        $this->client->getCookieJar()->clear();

        $this->uploadPhoto();

        self::assertResponseStatusCodeSame(401);
    }
}
