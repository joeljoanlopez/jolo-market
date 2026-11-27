<?php

declare(strict_types=1);

namespace App\Catalog\Application\FindUnmappedCategories;

interface StoredCategories
{
    /**
     * @return list<CategoryCount> every distinct category path of the stored products, available or not,
     *                             in no particular order; products without a category are left out
     */
    public function counts(): array;
}
