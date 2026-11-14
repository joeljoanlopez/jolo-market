<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Dev;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:dev:users',
    description: 'Development only: create the dummy users "admin" and "usuario" that are missing (PD-56).',
)]
final readonly class CreateDevUsersConsoleCommand
{
    public function __construct(private DevUsers $devUsers)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        foreach ($this->devUsers->createMissing() as $username => $created) {
            $io->writeln($created
                ? \sprintf('Created "%s" with the password %s. Get a login code with: bin/console app:dev:totp %s', $username, DevUsers::PASSWORD, $username)
                : \sprintf('"%s" already exists: left untouched.', $username));
        }

        return Command::SUCCESS;
    }
}
