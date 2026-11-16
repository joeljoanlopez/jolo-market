<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Infrastructure\Dev;

use App\Tests\Support\Dev\IsolatedKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;

/**
 * PD-57: the app:dev:* commands and the dummy sources exist only in APP_ENV=dev.
 */
final class DevOnlyCodeTest extends TestCase
{
    private const array DEV_COMMANDS = [
        'app:dev:catalog:backfill',
        'app:dev:catalog:reset',
        'app:dev:lists:reset',
        'app:dev:seed',
        'app:dev:totp',
        'app:dev:users',
        'app:dev:users:reset',
    ];

    /** Namespaces of the development-only classes. */
    private const string DEV_CLASSES = '~App\\\\(Catalog\\\\Infrastructure\\\\Source\\\\Dummy|[A-Za-z]+\\\\Infrastructure\\\\Dev)\\\\~';

    /** @var list<IsolatedKernel> */
    private array $kernels = [];

    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }
    }

    #[Test]
    public function the_dev_environment_has_every_dev_command_and_the_dummy_sources(): void
    {
        $kernel = $this->boot('dev');

        self::assertSame(self::DEV_COMMANDS, $this->devCommands($kernel));
        self::assertMatchesRegularExpression(self::DEV_CLASSES, $kernel->containerXml());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherEnvironments(): iterable
    {
        yield 'test' => ['test'];
        yield 'prod' => ['prod'];
    }

    #[Test]
    #[DataProvider('otherEnvironments')]
    public function no_other_environment_defines_them(string $environment): void
    {
        $kernel = $this->boot($environment);

        self::assertSame([], $this->devCommands($kernel));
        self::assertDoesNotMatchRegularExpression(self::DEV_CLASSES, $kernel->containerXml());
    }

    private function boot(string $environment): IsolatedKernel
    {
        $kernel = new IsolatedKernel($environment);
        $kernel->boot();
        $this->kernels[] = $kernel;

        return $kernel;
    }

    /**
     * @return list<string>
     */
    private function devCommands(IsolatedKernel $kernel): array
    {
        $names = array_values(array_filter(
            array_keys(new Application($kernel)->all()),
            static fn (string $name): bool => str_starts_with($name, 'app:dev:'),
        ));
        sort($names);

        return $names;
    }
}
