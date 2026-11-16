<?php

declare(strict_types=1);

namespace App\Tests\Support\Dev;

use App\Kernel;

/**
 * The application in any environment, with its own cache and log directories, so booting the
 * "prod" or "dev" environment from a test never touches the ones a developer uses. Debug is on,
 * so the compiled container is also dumped as XML (see containerXml()).
 */
final class IsolatedKernel extends Kernel
{
    public function __construct(string $environment)
    {
        parent::__construct($environment, true);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment.'_isolated';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log/'.$this->environment.'_isolated';
    }

    /**
     * Every service definition of the compiled container, inlined ones included.
     */
    public function containerXml(): string
    {
        $path = $this->getContainer()->getParameter('debug.container.dump');
        if (!\is_string($path) || !is_file($path)) {
            throw new \RuntimeException('The container was not dumped as XML.');
        }

        return (string) file_get_contents($path);
    }
}
