<?php

declare(strict_types=1);

namespace App\Tests\Functional\ShoppingList\Infrastructure\Api;

use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\Test;

final class ListsAndJoinApiTest extends ShoppingListApiTestCase
{
    #[Test]
    public function a_user_creates_a_list_and_owns_it(): void
    {
        $this->actAs('maria');

        $this->send('POST', '/api/lists', ['name' => '  Compra semanal ']);

        self::assertResponseStatusCodeSame(201);
        $id = $this->json()['id'] ?? null;
        self::assertIsString($id);
        $list = $this->storedList($id);
        self::assertSame($this->maria, $list->ownerId()->value());
        self::assertSame('Compra semanal', $list->name()->value());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $list->shareToken()->value());
    }

    #[Test]
    public function an_invalid_or_missing_name_is_a_422(): void
    {
        $this->actAs('maria');

        $this->send('POST', '/api/lists', ['name' => str_repeat('a', 81)]);
        $this->assertProblem(422);

        $this->send('POST', '/api/lists', []);
        $this->assertProblem(422);
    }

    #[Test]
    public function creating_a_list_needs_a_session(): void
    {
        $this->send('POST', '/api/lists', ['name' => 'Compra']);

        $this->assertProblem(401);
    }

    #[Test]
    public function my_lists_shows_the_lists_i_own_or_joined_with_my_role(): void
    {
        $this->storeSharedList();
        $this->storeTextItem('pan');
        $this->actAs('joan');
        $this->send('POST', '/api/lists', ['name' => 'De Joan']);
        $joans = $this->json()['id'] ?? null;

        $this->client->request('GET', '/api/lists');

        self::assertResponseStatusCodeSame(200);
        $lists = $this->json();
        self::assertCount(2, $lists);
        self::assertIsArray($lists[0]);
        self::assertIsArray($lists[1]);
        self::assertSame($joans, $lists[0]['id']);
        self::assertSame(['De Joan', 'owner', 'joan', 0], [$lists[0]['name'], $lists[0]['role'], $lists[0]['ownerUsername'], $lists[0]['itemCount']]);
        self::assertSame(
            ['id' => self::LIST, 'name' => 'Compra semanal', 'role' => 'member', 'ownerUsername' => 'maria', 'itemCount' => 1],
            array_diff_key($lists[1], ['updatedAt' => true]),
        );
        self::assertIsString($lists[1]['updatedAt']);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DATE_ATOM, $lists[1]['updatedAt']));
    }

    #[Test]
    public function an_outsider_does_not_see_the_list_in_their_lists(): void
    {
        $this->storeSharedList();
        $this->actAs('pedro');

        $this->client->request('GET', '/api/lists');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->json());
    }

    #[Test]
    public function opening_the_link_makes_the_user_a_member_idempotently(): void
    {
        $this->storeSharedList();
        $this->actAs('pedro');

        $this->send('POST', '/api/lists/join/'.self::TOKEN);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['id' => self::LIST], $this->json());

        $this->send('POST', '/api/lists/join/'.self::TOKEN);
        self::assertResponseStatusCodeSame(200);

        self::assertTrue($this->storedList()->canBeAccessedBy(UserId::fromString($this->pedro)));
        self::assertCount(2, $this->storedList()->members());
    }

    #[Test]
    public function the_owner_and_members_joining_change_nothing(): void
    {
        $version = $this->storeSharedList()->version();
        $this->actAs('maria');
        $this->send('POST', '/api/lists/join/'.self::TOKEN);
        self::assertResponseStatusCodeSame(200);

        $this->actAs('joan');
        $this->send('POST', '/api/lists/join/'.self::TOKEN);
        self::assertResponseStatusCodeSame(200);

        self::assertSame($version, $this->storedList()->version());
    }

    #[Test]
    public function an_unknown_or_malformed_link_is_a_404(): void
    {
        $this->storeSharedList();
        $this->actAs('pedro');

        $this->send('POST', '/api/lists/join/Zx9vQm3kT8wLr2Nf6Yb1Hc4Pj7Sd0Ga5Ue8Wq2Ki3Mo');
        $this->assertProblem(404);
        self::assertStringNotContainsString('Zx9vQm3k', (string) $this->client->getResponse()->getContent());

        $this->send('POST', '/api/lists/join/short');
        $this->assertProblem(404);
    }

    #[Test]
    public function a_revoked_link_is_a_404_and_the_new_one_works(): void
    {
        $this->storeSharedList();
        $this->actAs('maria');
        $this->send('POST', '/api/lists/'.self::LIST.'/share-token');
        $newToken = $this->json()['shareToken'] ?? null;
        self::assertIsString($newToken);

        $this->actAs('pedro');
        $this->send('POST', '/api/lists/join/'.self::TOKEN);
        $this->assertProblem(404);

        $this->send('POST', '/api/lists/join/'.$newToken);
        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function a_link_that_looks_like_another_route_still_joins(): void
    {
        $this->storeSharedList();
        $this->actAs('pedro');

        $this->send('POST', '/api/lists/join/leave');

        $this->assertProblem(404);
    }
}
