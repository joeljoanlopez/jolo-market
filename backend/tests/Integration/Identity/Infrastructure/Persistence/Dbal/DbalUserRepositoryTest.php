<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity\Infrastructure\Persistence\Dbal;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\EncryptedTotpSecret;
use App\Identity\Domain\ValueObject\PasswordHash;
use App\Identity\Domain\ValueObject\Username;
use App\Identity\Infrastructure\Persistence\Dbal\DbalUserRepository;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Support\Identity\Users;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalUserRepositoryTest extends KernelTestCase
{
    private const string ADMIN = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string PEDRO = '0192f3a4-7b1c-7d2e-8f30-000000000003';
    private const string TOKEN = 'activation-token-0000000000000000000042';

    private Connection $connection;
    private DbalUserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM user');
        $this->repository = new DbalUserRepository($this->connection);
    }

    #[Test]
    public function it_saves_a_pending_user_and_finds_it_by_id(): void
    {
        $pedro = Users::pending(self::PEDRO, 'pedro', self::TOKEN);

        $this->repository->save($pedro);

        $found = $this->repository->findById(UserId::fromString(self::PEDRO)) ?? self::fail('Not found.');
        self::assertSame('pedro', $found->username()->value());
        self::assertSame(Role::USER, $found->role());
        self::assertSame(UserStatus::PENDING_ACTIVATION, $found->status());
        self::assertNull($found->passwordHash());
        self::assertNull($found->totpSecret());
        self::assertNull($found->createdBy());
        self::assertSame(1, $found->sessionVersion());
        self::assertTrue($found->activation()?->matches(self::TOKEN));
        self::assertEquals(Users::at('2026-11-12 10:00:00'), $found->activation()->expiresAt());
        self::assertEquals(Users::at(Users::CREATED_AT), $found->createdAt());
    }

    #[Test]
    public function it_saves_every_field_of_an_active_user(): void
    {
        $this->repository->save(Users::active(self::ADMIN, 'admin', Role::ADMIN));
        $maria = User::reconstitute(
            UserId::fromString(self::MARIA),
            Username::fromString('maria'),
            Role::USER,
            UserStatus::ACTIVE,
            PasswordHash::fromString('$argon2id$hash'),
            EncryptedTotpSecret::fromString('cipher-current'),
            Users::at('2026-11-10 09:00:00.123456'),
            EncryptedTotpSecret::fromString('cipher-pending'),
            null,
            Users::at(Users::CREATED_AT),
            UserId::fromString(self::ADMIN),
            4,
        );

        $this->repository->save($maria);

        $found = $this->repository->findById(UserId::fromString(self::MARIA)) ?? self::fail('Not found.');
        self::assertEquals($maria, $found);
    }

    #[Test]
    public function saving_again_updates_the_user(): void
    {
        $user = Users::active(self::MARIA, 'maria');
        $this->repository->save($user);

        $user->changeRole(Role::ADMIN);
        $user->disable();
        $this->repository->save($user);

        $found = $this->repository->findById(UserId::fromString(self::MARIA)) ?? self::fail('Not found.');
        self::assertSame(Role::ADMIN, $found->role());
        self::assertSame(UserStatus::DISABLED, $found->status());
        self::assertSame(2, $found->sessionVersion());
        self::assertSame(1, $this->countUsers());
    }

    #[Test]
    public function it_finds_a_user_by_username(): void
    {
        $this->repository->save(Users::active(self::MARIA, 'maria'));

        self::assertSame(self::MARIA, $this->repository->findByUsername(Username::fromString('MARIA'))?->id()->value());
        self::assertNull($this->repository->findByUsername(Username::fromString('pedro')));
    }

    #[Test]
    public function two_users_cannot_share_a_username(): void
    {
        $this->repository->save(Users::active(self::MARIA, 'maria'));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->repository->save(Users::active(self::PEDRO, 'maria'));
    }

    #[Test]
    public function it_finds_a_user_by_the_hash_of_their_current_activation_token(): void
    {
        $this->repository->save(Users::pending(self::PEDRO, 'pedro', self::TOKEN));

        self::assertSame(self::PEDRO, $this->repository->findByActivationTokenHash(ActivationToken::hashOf(self::TOKEN))?->id()->value());
        self::assertNull($this->repository->findByActivationTokenHash(ActivationToken::hashOf('another-token-another-token-another')));
    }

    #[Test]
    public function it_finds_users_by_ids_and_skips_unknown_ones(): void
    {
        $this->repository->save(Users::active(self::MARIA, 'maria'));
        $this->repository->save(Users::active(self::ADMIN, 'admin', Role::ADMIN));

        $found = $this->repository->findByIds([UserId::fromString(self::MARIA), UserId::fromString(self::PEDRO)]);

        self::assertSame([self::MARIA], array_map(static fn (User $user): string => $user->id()->value(), $found));
        self::assertSame([], $this->repository->findByIds([]));
    }

    #[Test]
    public function it_lists_every_user_ordered_by_username(): void
    {
        $this->repository->save(Users::pending(self::PEDRO, 'pedro', self::TOKEN));
        $this->repository->save(Users::active(self::MARIA, 'maria'));
        $this->repository->save(Users::active(self::ADMIN, 'admin', Role::ADMIN));

        self::assertSame(
            ['admin', 'maria', 'pedro'],
            array_map(static fn (User $user): string => $user->username()->value(), $this->repository->findAll()),
        );
    }

    #[Test]
    public function it_counts_only_active_admins(): void
    {
        $this->repository->save(Users::active(self::ADMIN, 'admin', Role::ADMIN));
        $this->repository->save(Users::pending(self::PEDRO, 'pedro', self::TOKEN, Role::ADMIN));
        $disabled = Users::active(self::MARIA, 'maria', Role::ADMIN);
        $disabled->disable();
        $this->repository->save($disabled);

        self::assertSame(1, $this->repository->countActiveAdmins());
    }

    #[Test]
    public function it_tells_whether_any_user_exists(): void
    {
        self::assertFalse($this->repository->hasAnyUser());

        $this->repository->save(Users::pending(self::PEDRO, 'pedro', self::TOKEN));

        self::assertTrue($this->repository->hasAnyUser());
    }

    private function countUsers(): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM user');
        self::assertIsNumeric($count);

        return (int) $count;
    }
}
