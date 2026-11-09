<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Source\Mercadona;

use App\Catalog\Infrastructure\Source\Mercadona\MercadonaCatalogSource;
use App\Tests\Support\Catalog\ConfigurableCatalogSource;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MercadonaWiringTest extends KernelTestCase
{
    #[Test]
    public function the_real_adapter_is_replaced_by_a_fake_in_tests(): void
    {
        self::bootKernel();

        self::assertInstanceOf(ConfigurableCatalogSource::class, self::getContainer()->get(MercadonaCatalogSource::class));
    }
}
