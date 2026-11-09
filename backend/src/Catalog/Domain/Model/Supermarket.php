<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * A supermarket chain. Whether it is enabled is instance configuration (ENABLED_SUPERMARKETS, PD-24), not stored state.
 */
final class Supermarket
{
    private function __construct(
        private readonly SupermarketId $id,
        private readonly string $name,
    ) {
        if ('' === trim($name)) {
            throw InvalidSupermarket::blankName();
        }
    }

    public static function create(SupermarketId $id, string $name): self
    {
        return new self($id, $name);
    }

    public function id(): SupermarketId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
