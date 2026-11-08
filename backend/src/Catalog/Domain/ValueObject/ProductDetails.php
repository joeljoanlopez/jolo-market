<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Descriptive data of a product, as its supermarket shows it. It changes between runs.
 */
final readonly class ProductDetails
{
    private function __construct(
        private ProductName $name,
        private ?string $brand,
        private ?CategoryPath $category,
        private ?Url $imageUrl,
    ) {
    }

    public static function of(ProductName $name, ?string $brand, ?CategoryPath $category, ?Url $imageUrl): self
    {
        $brand = null === $brand ? null : trim($brand);

        return new self($name, '' === $brand ? null : $brand, $category, $imageUrl);
    }

    public function name(): ProductName
    {
        return $this->name;
    }

    public function brand(): ?string
    {
        return $this->brand;
    }

    public function category(): ?CategoryPath
    {
        return $this->category;
    }

    public function imageUrl(): ?Url
    {
        return $this->imageUrl;
    }
}
