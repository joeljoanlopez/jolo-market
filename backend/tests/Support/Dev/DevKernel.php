<?php

declare(strict_types=1);

namespace App\Tests\Support\Dev;

use App\Kernel;
use App\Shared\Domain\Clock;
use App\Tests\Support\FixedClock;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The application in the "dev" environment, as tests need it: the development-only services
 * (PD-57) are there, but it uses the test database and its own cache and log directories, so a
 * test never touches the developer's data or search index. Its clock is frozen when the kernel boots.
 */
final class DevKernel extends Kernel
{
    /** Private services that tests fetch from the container. */
    private const array PUBLIC_FOR_TESTS = ['catalog.dev.sources'];

    public function __construct()
    {
        // Debug, as in the test environment, so the container is rebuilt when the configuration changes.
        parent::__construct('dev', true);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/dev_test';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log/dev_test';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'test' => true,
                'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            ]);
            $container->loadFromExtension('doctrine', ['dbal' => ['dbname_suffix' => '_test']]);
            // Its own search index too, never the developer's (PD-100).
            $container->setParameter('catalog.search_alias', 'products_dev_test');

            $container->register('test.dev.clock', FixedClock::class)
                ->setFactory([FixedClock::class, 'at'])
                ->setArguments(['now'])
                ->setPublic(true);
            $container->setAlias(Clock::class, 'test.dev.clock')->setPublic(true);
        });
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new MakeServicesPublicPass(self::PUBLIC_FOR_TESTS), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }
}
