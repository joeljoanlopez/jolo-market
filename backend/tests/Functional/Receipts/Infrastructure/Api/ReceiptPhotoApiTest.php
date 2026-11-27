<?php

declare(strict_types=1);

namespace App\Tests\Functional\Receipts\Infrastructure\Api;

use PHPUnit\Framework\Attributes\Test;

final class ReceiptPhotoApiTest extends ReceiptsApiTestCase
{
    private const string PNG = "\x89PNG\r\n\x1A\npng-bytes";

    #[Test]
    public function a_member_gets_the_stored_photo_with_its_type_privately_cached(): void
    {
        $this->actAs('maria');
        $this->uploadPhoto(self::PNG, fileName: 'ticket.jpg');
        $id = $this->json()['id'];
        self::assertIsString($id);
        $this->actAs('joan');

        $this->client->request('GET', '/api/lists/'.self::LIST.'/receipts/'.$id.'/photo');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertSame(self::PNG, $this->client->getInternalResponse()->getContent());
    }

    #[Test]
    public function an_outsider_gets_404(): void
    {
        $this->actAs('maria');
        $this->uploadPhoto();
        $id = $this->json()['id'];
        self::assertIsString($id);
        $this->actAs('pedro');

        $this->client->request('GET', '/api/lists/'.self::LIST.'/receipts/'.$id.'/photo');

        $this->assertProblem(404);
    }
}
