<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity\Infrastructure\Persistence\Dbal;

use App\Identity\Domain\ValueObject\SetupCode;
use App\Identity\Infrastructure\Persistence\Dbal\DbalSetupCodeRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalSetupCodeRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private DbalSetupCodeRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM setup_code');
        $this->repository = new DbalSetupCodeRepository($this->connection);
    }

    #[Test]
    public function it_keeps_only_the_latest_code(): void
    {
        self::assertNull($this->repository->find());

        $this->repository->replace(SetupCode::issue('AAAA-AAAA-AAAA-AAA1', new \DateTimeImmutable('2026-11-09 08:00:00')));
        $this->repository->replace(SetupCode::issue('AAAA-AAAA-AAAA-AAA2', new \DateTimeImmutable('2026-11-09 09:00:00')));

        $found = $this->repository->find() ?? self::fail('No code.');
        self::assertTrue($found->matches('AAAA-AAAA-AAAA-AAA2'));
        self::assertFalse($found->matches('AAAA-AAAA-AAAA-AAA1'));
        self::assertEquals(new \DateTimeImmutable('2026-11-09 09:00:00'), $found->createdAt());
        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(*) FROM setup_code'));
    }

    #[Test]
    public function it_deletes_the_code(): void
    {
        $this->repository->replace(SetupCode::issue('AAAA-AAAA-AAAA-AAA1', new \DateTimeImmutable()));

        $this->repository->delete();

        self::assertNull($this->repository->find());
    }
}
