<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Dev;

use App\Catalog\Infrastructure\Dev\DevCatalogSources;
use App\Tests\Support\Catalog\FakeCatalogSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DevCatalogSourcesTest extends TestCase
{
    private FakeCatalogSource $real;
    private FakeCatalogSource $dummy;

    protected function setUp(): void
    {
        $this->real = new FakeCatalogSource('mercadona', []);
        $this->dummy = new FakeCatalogSource('mercadona', []);
    }

    #[Test]
    public function it_gives_the_dummy_sources_by_default(): void
    {
        $sources = new DevCatalogSources('dummy', [$this->real], [$this->dummy]);

        self::assertSame([$this->dummy], [...$sources]);
    }

    #[Test]
    public function it_gives_the_real_sources_when_asked_to(): void
    {
        $sources = new DevCatalogSources('real', [$this->real], [$this->dummy]);

        self::assertSame([$this->real], [...$sources]);
    }

    #[Test]
    public function it_rejects_any_other_choice(): void
    {
        $sources = new DevCatalogSources('fake', [$this->real], [$this->dummy]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CATALOG_SOURCES must be "dummy" or "real", got "fake".');

        iterator_to_array($sources);
    }
}
