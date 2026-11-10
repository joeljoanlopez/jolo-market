<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use App\Identity\Application\Setup\EnsureSetupCodeHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:setup:ensure-code',
    description: 'While there are no users, print a new one-time setup code for the first admin (PD-14). Run at container start.',
)]
final readonly class EnsureSetupCodeConsoleCommand
{
    public function __construct(private EnsureSetupCodeHandler $ensureSetupCode)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $code = ($this->ensureSetupCode)();
        if (null === $code) {
            $io->writeln('Users exist: first-admin setup is closed.');

            return Command::SUCCESS;
        }

        $io->writeln('No users yet. Open the app, go to /setup and enter this one-time code (it changes on each start):');
        $io->writeln('Setup code: '.$code);

        return Command::SUCCESS;
    }
}
