<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\ValueObject\SupermarketId;

final class Supermarket
{
    private function __construct(
        private readonly SupermarketId $id,
        private readonly string $name,
        private readonly bool $enabled,
    ) {
        if ('' === trim($name)) {
            throw InvalidSupermarket::blankName();
        }
    }

    public static function create(SupermarketId $id, string $name, bool $enabled): self
    {
        return new self($id, $name, $enabled);
    }

    public function id(): SupermarketId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
