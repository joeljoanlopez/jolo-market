<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Dbal;

use App\Identity\Domain\Port\SetupCodeRepository;
use App\Identity\Domain\ValueObject\SetupCode;
use App\Shared\Infrastructure\Persistence\Dbal\DbalDateTime;
use Doctrine\DBAL\Connection;

final readonly class DbalSetupCodeRepository implements SetupCodeRepository
{
    private const int ONLY_ROW = 1;

    public function __construct(private Connection $connection)
    {
    }

    public function replace(SetupCode $code): void
    {
        $this->connection->executeStatement(
            'REPLACE INTO setup_code (id, code_hash, created_at) VALUES (?, ?, ?)',
            [self::ONLY_ROW, $code->hash(), DbalDateTime::toDatabase($code->createdAt())],
        );
    }

    public function find(): ?SetupCode
    {
        $row = $this->connection->fetchAssociative('SELECT code_hash, created_at FROM setup_code WHERE id = ?', [self::ONLY_ROW]);
        if (false === $row || !\is_string($row['code_hash']) || !\is_string($row['created_at'])) {
            return null;
        }

        return SetupCode::reconstitute($row['code_hash'], DbalDateTime::fromDatabase($row['created_at']));
    }

    public function delete(): void
    {
        $this->connection->executeStatement('DELETE FROM setup_code');
    }
}
