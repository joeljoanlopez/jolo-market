<?php

declare(strict_types=1);

namespace App\Tests\Functional\ShoppingList\Infrastructure\Api;

use App\ShoppingList\Domain\Model\FreeTextItem;
use App\ShoppingList\Domain\Model\ListItem;
use PHPUnit\Framework\Attributes\Test;

final class ListItemsApiTest extends ShoppingListApiTestCase
{
    #[Test]
    public function owner_and_members_add_free_text_items(): void
    {
        $this->storeSharedList();

        foreach (['maria' => ['pan', null], 'joan' => ['huevos', 12]] as $username => [$text, $quantity]) {
            $this->actAs($username);
            $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'text', 'text' => $text, 'quantity' => $quantity]);
            self::assertResponseStatusCodeSame(201);
            self::assertIsString($this->json()['id'] ?? null);
        }

        $items = $this->storedList()->items();
        self::assertSame(['pan', 'huevos'], array_map(self::text(...), $items));
        self::assertSame([1, 12], array_map(static fn (ListItem $item): int => $item->quantity()->value(), $items));
    }

    #[Test]
    public function adding_an_available_product_already_in_the_list_adds_to_its_quantity(): void
    {
        $milk = $this->seedProduct(1, 'Leche entera', 125);
        $this->storeSharedList();
        $this->actAs('joan');

        $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $milk, 'quantity' => 2]);
        $first = $this->json()['id'] ?? null;
        $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $milk]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($first, $this->json()['id'] ?? null);
        self::assertCount(1, $this->storedList()->items());
        self::assertSame(3, $this->storedList()->items()[0]->quantity()->value());
    }

    #[Test]
    public function invalid_items_are_a_422(): void
    {
        $gone = $this->seedProduct(1, 'Leche', 125, available: false);
        $this->storeSharedList();
        $this->actAs('maria');

        foreach ([
            ['type' => 'text', 'text' => ' '],
            ['type' => 'text', 'text' => str_repeat('a', 121)],
            ['type' => 'text'],
            ['type' => 'text', 'text' => 'pan', 'quantity' => 0],
            ['type' => 'text', 'text' => 'pan', 'quantity' => 100],
            ['type' => 'text', 'text' => 'pan', 'quantity' => '2'],
            ['type' => 'product', 'productId' => $gone],
            ['type' => 'product', 'productId' => '0191bbbb-0000-7000-8000-000000000099'],
            ['type' => 'product', 'productId' => 'not-a-uuid'],
            ['type' => 'product'],
            ['type' => 'recipe', 'text' => 'pan'],
            ['text' => 'pan'],
        ] as $body) {
            $this->send('POST', '/api/lists/'.self::LIST.'/items', $body);
            $this->assertProblem(422);
        }
        self::assertSame([], $this->storedList()->items());
    }

    #[Test]
    public function merging_a_product_above_99_is_a_422(): void
    {
        $milk = $this->seedProduct(1, 'Leche entera', 125);
        $this->storeSharedList();
        $this->actAs('maria');
        $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $milk, 'quantity' => 98]);

        $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $milk, 'quantity' => 2]);

        $this->assertProblem(422);
    }

    #[Test]
    public function a_member_updates_quantity_checked_and_position(): void
    {
        $this->storeSharedList();
        $bread = $this->storeTextItem('pan');
        $eggs = $this->storeTextItem('huevos');
        $this->actAs('joan');

        $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$eggs, ['quantity' => 6, 'checked' => true, 'position' => 0]);

        self::assertResponseStatusCodeSame(204);
        $items = $this->storedList()->items();
        self::assertSame([$eggs, $bread], array_map(static fn (ListItem $item): string => $item->id()->value(), $items));
        self::assertSame(6, $items[0]->quantity()->value());
        self::assertTrue($items[0]->isChecked());
    }

    #[Test]
    public function checking_sets_and_unchecking_clears(): void
    {
        $this->storeSharedList();
        $bread = $this->storeTextItem('pan');
        $this->actAs('maria');

        $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$bread, ['checked' => true]);
        $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$bread, ['checked' => true]);
        self::assertTrue($this->storedList()->items()[0]->isChecked());

        $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$bread, ['checked' => false]);
        self::assertResponseStatusCodeSame(204);
        self::assertFalse($this->storedList()->items()[0]->isChecked());
    }

    #[Test]
    public function invalid_updates_are_a_422(): void
    {
        $this->storeSharedList();
        $bread = $this->storeTextItem('pan');
        $this->actAs('maria');

        foreach ([['quantity' => 0], ['quantity' => 'many'], ['checked' => 'yes'], ['position' => -1], ['position' => 1.5]] as $body) {
            $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$bread, $body);
            $this->assertProblem(422);
        }
    }

    #[Test]
    public function each_item_is_shown_in_its_section(): void
    {
        $milk = $this->seedProduct(1, 'Leche entera', 125, category: 'Lácteos y huevos > Leche');
        $toy = $this->seedProduct(2, 'Puzle', 999, category: 'Juguetes > Puzles');
        $salt = $this->seedProduct(3, 'Sal', 35);
        $this->storeSharedList();
        $this->storeTextItem('pan');
        $this->actAs('maria');
        foreach ([$milk, $toy, $salt] as $product) {
            $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $product]);
        }

        $items = $this->itemsJson();

        self::assertSame(['other', 'dairy-and-eggs', 'other', 'other'], array_column($items, 'section'));
        self::assertSame([null, null, null, null], array_column($items, 'sectionOverride'));
        self::assertSame(['dairy-and-eggs', 'other', 'other'], array_column(array_column(\array_slice($items, 1), 'product'), 'section'));
    }

    #[Test]
    public function a_product_removed_from_the_catalog_is_in_other(): void
    {
        $milk = $this->seedProduct(1, 'Leche entera', 125, category: 'Lácteos y huevos > Leche');
        $this->storeSharedList();
        $this->actAs('maria');
        $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $milk]);
        $this->deleteAllProducts();

        $items = $this->itemsJson();

        self::assertSame(['other', null, 'other'], self::sections($items[0]));
    }

    #[Test]
    public function a_member_chooses_and_removes_the_section_of_an_item(): void
    {
        $milk = $this->seedProduct(1, 'Leche entera', 125, category: 'Lácteos y huevos > Leche');
        $this->storeSharedList();
        $this->actAs('joan');
        $this->send('POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $milk]);
        $item = $this->json()['id'] ?? null;
        self::assertIsString($item);
        $version = $this->storedList()->version();

        $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$item, ['section' => 'drinks']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame($version + 1, $this->storedList()->version());
        self::assertSame(['drinks', 'drinks', 'dairy-and-eggs'], self::sections($this->itemsJson()[0]));

        $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$item, ['section' => null]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['dairy-and-eggs', null, 'dairy-and-eggs'], self::sections($this->itemsJson()[0]));
    }

    #[Test]
    public function a_patch_without_section_keeps_the_chosen_one(): void
    {
        $this->storeSharedList();
        $bread = $this->storeTextItem('pan');
        $this->actAs('maria');
        $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$bread, ['section' => 'bakery']);

        $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$bread, ['quantity' => 2]);

        self::assertSame('bakery', $this->itemsJson()[0]['sectionOverride'] ?? null);
    }

    #[Test]
    public function an_unknown_section_is_a_422_and_an_unknown_item_a_404(): void
    {
        $this->storeSharedList();
        $bread = $this->storeTextItem('pan');
        $this->actAs('maria');

        foreach ([['section' => 'toys'], ['section' => 3]] as $body) {
            $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$bread, $body);
            $this->assertProblem(422);
        }
        self::assertNull($this->storedList()->items()[0]->section());

        $this->send('PATCH', '/api/lists/'.self::LIST.'/items/0193cccc-0000-7000-8000-000000000099', ['section' => 'bakery']);
        $this->assertProblem(404);
    }

    #[Test]
    public function an_unknown_item_is_a_404(): void
    {
        $this->storeSharedList();
        $this->actAs('maria');

        foreach (['0193cccc-0000-7000-8000-000000000099', 'not-a-uuid'] as $itemId) {
            $this->send('PATCH', '/api/lists/'.self::LIST.'/items/'.$itemId, ['checked' => true]);
            $this->assertProblem(404);
            $this->send('DELETE', '/api/lists/'.self::LIST.'/items/'.$itemId);
            $this->assertProblem(404);
        }
    }

    #[Test]
    public function a_member_removes_an_item(): void
    {
        $this->storeSharedList();
        $bread = $this->storeTextItem('pan');
        $this->actAs('joan');

        $this->send('DELETE', '/api/lists/'.self::LIST.'/items/'.$bread);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->storedList()->items());
    }

    #[Test]
    public function clearing_checked_items(): void
    {
        $this->storeSharedList();
        $this->storeTextItem('pan', checked: true);
        $this->storeTextItem('huevos');
        $this->actAs('joan');

        $this->send('DELETE', '/api/lists/'.self::LIST.'/items?checked=true');

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['huevos'], array_map(self::text(...), $this->storedList()->items()));
    }

    #[Test]
    public function clearing_without_checked_true_is_a_422_and_removes_nothing(): void
    {
        $this->storeSharedList();
        $this->storeTextItem('pan', checked: true);
        $this->actAs('maria');

        $this->send('DELETE', '/api/lists/'.self::LIST.'/items');
        $this->assertProblem(422);

        self::assertCount(1, $this->storedList()->items());
    }

    #[Test]
    public function an_outsider_gets_a_404_on_every_item_route(): void
    {
        $milk = $this->seedProduct(1, 'Leche entera', 125);
        $this->storeSharedList();
        $bread = $this->storeTextItem('pan', checked: true);
        $this->actAs('pedro');

        foreach ([
            ['POST', '/api/lists/'.self::LIST.'/items', ['type' => 'text', 'text' => 'pan']],
            ['POST', '/api/lists/'.self::LIST.'/items', ['type' => 'product', 'productId' => $milk]],
            ['PATCH', '/api/lists/'.self::LIST.'/items/'.$bread, ['checked' => false]],
            ['PATCH', '/api/lists/'.self::LIST.'/items/not-a-uuid', ['checked' => false]],
            ['DELETE', '/api/lists/'.self::LIST.'/items/'.$bread, null],
            ['DELETE', '/api/lists/'.self::LIST.'/items?checked=true', null],
        ] as [$method, $uri, $body]) {
            $this->send($method, $uri, $body);
            $this->assertProblem(404);
        }

        self::assertCount(1, $this->storedList()->items());
        self::assertTrue($this->storedList()->items()[0]->isChecked());
    }

    /**
     * @return list<array<mixed>>
     */
    private function itemsJson(): array
    {
        $this->client->request('GET', '/api/lists/'.self::LIST);
        self::assertResponseStatusCodeSame(200);
        $items = [];
        foreach ((array) ($this->json()['items'] ?? []) as $item) {
            self::assertIsArray($item);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<mixed> $item
     *
     * @return array{mixed, mixed, mixed} the section shown, the one chosen by hand and the catalog one
     */
    private static function sections(array $item): array
    {
        $product = $item['product'] ?? null;

        return [$item['section'] ?? null, $item['sectionOverride'] ?? null, \is_array($product) ? $product['section'] ?? null : null];
    }

    private static function text(ListItem $item): string
    {
        $content = $item->content();

        return $content instanceof FreeTextItem ? $content->text->value() : '';
    }
}
