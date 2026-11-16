<?php

declare(strict_types=1);

namespace App\Tests\Support\Dev;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * For KernelTestCase and WebTestCase classes that test development-only code: the kernel is
 * DevKernel, and environment variables can be changed for one test (they are read at runtime).
 * The test environment enables only two chains, so the three are enabled here, as in dev.
 */
trait DevEnvironment
{
    /** @var array<string, string|null> the values to restore, null when the variable was not set */
    private array $previousVariables = [];

    protected static function getKernelClass(): string
    {
        return DevKernel::class;
    }

    /**
     * @param array<mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new DevKernel();
    }

    protected function setUpDevEnvironment(): void
    {
        $this->setVariable('ENABLED_SUPERMARKETS', 'mercadona,bonpreu,aldi');
    }

    protected function setVariable(string $name, string $value): void
    {
        if (!\array_key_exists($name, $this->previousVariables)) {
            $previous = $_SERVER[$name] ?? null;
            $this->previousVariables[$name] = \is_string($previous) ? $previous : null;
        }

        $_SERVER[$name] = $_ENV[$name] = $value;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->previousVariables as $name => $value) {
            if (null === $value) {
                unset($_SERVER[$name], $_ENV[$name]);
            } else {
                $_SERVER[$name] = $_ENV[$name] = $value;
            }
        }
        $this->previousVariables = [];
    }
}
