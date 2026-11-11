<?php

declare(strict_types=1);

namespace App\Tests\Functional\ShoppingList\Infrastructure\Api;

use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\Test;

final class ManageListApiTest extends ShoppingListApiTestCase
{
    #[Test]
    public function the_owner_gets_the_whole_list_with_its_etag(): void
    {
        $milk = $this->seedProduct(1, 'Leche entera', 125, brand: 'Hacendado');
        $bread = $this->seedProduct(2, 'Pan', 89, 'aldi');
        $gone = $this->seedProduct(3, 'Leche desnatada', 99, 'mercadona');
        $this->storeSharedList();
        $text = $this->storeTextItem('servilletas');
        $this->actAs('maria');
        $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $milk, 'quantity' => 2]);
        $milkItem = $this->json()['id'] ?? null;
        $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $bread]);
        $breadItem = $this->json()['id'] ?? null;
        self::assertIsString($breadItem);
        $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $gone]);
        $goneItem = $this->json()['id'] ?? null;
        $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$breadItem, ['checked' => true]);
        $this->markUnavailable($gone);
        $version = $this->storedList()->version();

        $this->client->request('GET', '/api/lists/'.self::LIST);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('ETag', '"'.$version.'"');
        self::assertSame([
            'id' => self::LIST,
            'name' => 'Compra semanal',
            'role' => 'owner',
            'version' => $version,
            'shareToken' => self::TOKEN,
            'owner' => ['id' => $this->maria, 'username' => 'maria'],
            'members' => [['id' => $this->joan, 'username' => 'joan']],
            'items' => [
                ['id' => $text, 'position' => 0, 'quantity' => 1, 'checked' => false, 'type' => 'text', 'text' => 'servilletas', 'product' => null],
                ['id' => $milkItem, 'position' => 1, 'quantity' => 2, 'checked' => false, 'type' => 'product', 'text' => null,
                    'product' => ['id' => $milk, 'name' => 'Leche entera', 'brand' => 'Hacendado', 'supermarketId' => 'mercadona', 'priceCents' => 125, 'imageUrl' => null, 'available' => true]],
                ['id' => $breadItem, 'position' => 2, 'quantity' => 1, 'checked' => true, 'type' => 'product', 'text' => null,
                    'product' => ['id' => $bread, 'name' => 'Pan', 'brand' => null, 'supermarketId' => 'aldi', 'priceCents' => 89, 'imageUrl' => null, 'available' => true]],
                ['id' => $goneItem, 'position' => 3, 'quantity' => 1, 'checked' => false, 'type' => 'product', 'text' => null,
                    'product' => ['id' => $gone, 'name' => 'Leche desnatada', 'brand' => null, 'supermarketId' => 'mercadona', 'priceCents' => 99, 'imageUrl' => null, 'available' => false]],
            ],
            'totals' => [
                ['supermarketId' => 'aldi', 'supermarketName' => 'Aldi', 'totalCents' => 89, 'itemCount' => 1],
                ['supermarketId' => 'mercadona', 'supermarketName' => 'Mercadona', 'totalCents' => 250, 'itemCount' => 1],
            ],
        ], $this->json());
    }

    #[Test]
    public function a_member_gets_the_list_without_the_link(): void
    {
        $this->storeSharedList();
        $this->actAs('joan');

        $this->client->request('GET', '/api/lists/'.self::LIST);

        self::assertResponseStatusCodeSame(200);
        $list = $this->json();
        self::assertSame('member', $list['role'] ?? null);
        self::assertArrayHasKey('shareToken', $list);
        self::assertNull($list['shareToken']);
    }

    #[Test]
    public function a_product_the_catalog_no_longer_knows_is_shown_unavailable_without_a_name(): void
    {
        $water = $this->seedProduct(1, 'Agua', 30);
        $this->storeSharedList();
        $this->actAs('maria');
        $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $water]);
        $this->deleteAllProducts();

        $this->client->request('GET', '/api/lists/'.self::LIST);

        $items = $this->json()['items'] ?? null;
        self::assertIsArray($items);
        self::assertIsArray($items[0]);
        self::assertSame(['id' => $water, 'name' => null, 'brand' => null, 'supermarketId' => null, 'priceCents' => null, 'imageUrl' => null, 'available' => false], $items[0]['product']);
        self::assertSame([], $this->json()['totals'] ?? null);
    }

    #[Test]
    public function the_same_version_is_a_304_and_a_change_gives_a_new_etag(): void
    {
        $this->storeSharedList();
        $this->actAs('joan');
        $this->client->request('GET', '/api/lists/'.self::LIST);
        $etag = $this->client->getResponse()->headers->get('ETag');
        self::assertIsString($etag);

        $this->client->request('GET', '/api/lists/'.self::LIST, server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);
        self::assertSame('', (string) $this->client->getResponse()->getContent());

        $this->storeTextItem('pan');
        $this->client->request('GET', '/api/lists/'.self::LIST, server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(200);
        self::assertNotSame($etag, $this->client->getResponse()->headers->get('ETag'));
    }

    #[Test]
    public function an_outsider_an_unknown_or_a_malformed_list_is_a_404(): void
    {
        $this->storeSharedList();
        $this->actAs('pedro');

        foreach ([self::LIST, self::UNKNOWN, 'not-a-uuid'] as $id) {
            $this->client->request('GET', '/api/lists/'.$id);
            $this->assertProblem(404);
        }
    }

    #[Test]
    public function the_owner_renames_the_list(): void
    {
        $this->storeSharedList();
        $this->actAs('maria');

        $this->send('PATCH', '/api/lists/'.self::LIST, ['name' => 'Fiesta']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('Fiesta', $this->storedList()->name()->value());
    }

    #[Test]
    public function an_invalid_name_is_a_422(): void
    {
        $this->storeSharedList();
        $this->actAs('maria');

        $this->send('PATCH', '/api/lists/'.self::LIST, ['name' => '']);

        $this->assertProblem(422);
    }

    #[Test]
    public function owner_only_routes_are_a_404_for_members_and_outsiders(): void
    {
        $this->storeSharedList();
        $routes = [
            ['PATCH', '/api/lists/'.self::LIST, ['name' => 'Fiesta']],
            ['DELETE', '/api/lists/'.self::LIST, null],
            ['POST', '/api/lists/'.self::LIST.'/share-token', null],
            ['DELETE', '/api/lists/'.self::LIST.'/members/'.$this->joan, null],
        ];

        foreach (['joan', 'pedro'] as $username) {
            $this->actAs($username);
            foreach ($routes as [$method, $uri, $body]) {
                $this->send($method, $uri, $body);
                $this->assertProblem(404);
            }
        }

        $list = $this->storedList();
        self::assertSame('Compra semanal', $list->name()->value());
        self::assertSame(self::TOKEN, $list->shareToken()->value());
        self::assertCount(1, $list->members());
    }

    #[Test]
    public function the_owner_regenerates_the_link_and_members_stay(): void
    {
        $this->storeSharedList();
        $this->actAs('maria');

        $this->send('POST', '/api/lists/'.self::LIST.'/share-token');

        self::assertResponseStatusCodeSame(200);
        $token = $this->json()['shareToken'] ?? null;
        self::assertIsString($token);
        self::assertNotSame(self::TOKEN, $token);
        self::assertSame($token, $this->storedList()->shareToken()->value());
        self::assertCount(1, $this->storedList()->members());
    }

    #[Test]
    public function the_owner_removes_a_member_who_then_gets_a_404(): void
    {
        $this->storeSharedList();
        $this->actAs('maria');

        $this->send('DELETE', '/api/lists/'.self::LIST.'/members/'.$this->joan);
        self::assertResponseStatusCodeSame(204);

        $this->actAs('joan');
        $this->client->request('GET', '/api/lists/'.self::LIST);
        $this->assertProblem(404);
    }

    #[Test]
    public function removing_someone_who_is_not_a_member_is_a_404(): void
    {
        $this->storeSharedList();
        $this->actAs('maria');

        $this->send('DELETE', '/api/lists/'.self::LIST.'/members/'.$this->pedro);
        $this->assertProblem(404);

        $this->send('DELETE', '/api/lists/'.self::LIST.'/members/not-a-uuid');
        $this->assertProblem(404);
    }

    #[Test]
    public function a_member_leaves_the_list(): void
    {
        $this->storeSharedList();
        $this->actAs('joan');

        $this->send('POST', '/api/lists/'.self::LIST.'/leave');

        self::assertResponseStatusCodeSame(204);
        self::assertFalse($this->storedList()->canBeAccessedBy(UserId::fromString($this->joan)));
    }

    #[Test]
    public function the_owner_leaving_is_a_409(): void
    {
        $this->storeSharedList();
        $this->actAs('maria');

        $this->send('POST', '/api/lists/'.self::LIST.'/leave');

        $this->assertProblem(409);
    }

    #[Test]
    public function an_outsider_leaving_is_a_404(): void
    {
        $this->storeSharedList();
        $this->actAs('pedro');

        $this->send('POST', '/api/lists/'.self::LIST.'/leave');

        $this->assertProblem(404);
    }

    #[Test]
    public function the_owner_deletes_the_list_and_members_then_get_a_404(): void
    {
        $this->storeSharedList();
        $this->storeTextItem('pan');
        $this->actAs('maria');

        $this->send('DELETE', '/api/lists/'.self::LIST);
        self::assertResponseStatusCodeSame(204);
        self::assertFalse($this->listExists());

        $this->actAs('joan');
        $this->client->request('GET', '/api/lists/'.self::LIST);
        $this->assertProblem(404);
        $this->client->request('GET', '/api/lists');
        self::assertSame([], $this->json());
    }

    #[Test]
    public function changes_need_the_csrf_header(): void
    {
        $this->storeSharedList();
        $this->actAs('maria');

        $this->client->request('DELETE', '/api/lists/'.self::LIST);

        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->listExists());
    }
}
