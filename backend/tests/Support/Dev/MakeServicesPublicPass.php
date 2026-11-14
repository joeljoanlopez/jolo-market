<?php

declare(strict_types=1);

namespace App\Tests\Support\Dev;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Keeps private services fetchable from the test container (they would otherwise be inlined).
 */
final readonly class MakeServicesPublicPass implements CompilerPassInterface
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($this->ids as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setPublic(true);
            }
        }
    }
}
