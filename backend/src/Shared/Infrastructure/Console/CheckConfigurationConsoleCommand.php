<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Configuration\ConfigurationCheck;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(
    name: 'app:config:check',
    description: 'Check the instance configuration (INSTANCE_POSTAL_CODE, ENABLED_SUPERMARKETS, TOTP_ENCRYPTION_KEY).',
)]
final readonly class CheckConfigurationConsoleCommand
{
    /**
     * @param iterable<ConfigurationCheck> $checks
     */
    public function __construct(
        #[AutowireIterator(ConfigurationCheck::TAG)]
        private iterable $checks,
    ) {
    }

    /**
     * Exit code: 0 when the configuration is valid, 1 otherwise. The production entrypoint stops on 1.
     */
    public function __invoke(SymfonyStyle $io): int
    {
        $problems = [];
        foreach ($this->checks as $check) {
            array_push($problems, ...$check->problems());
        }

        if ([] !== $problems) {
            $io->error(['The configuration is not valid. Fix these variables in the root .env and restart:', ...$problems]);

            return Command::FAILURE;
        }

        $io->success('The configuration is valid.');

        return Command::SUCCESS;
    }
}
