<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use App\Identity\Application\ManageUsers\ResetUserByUsernameCommand;
use App\Identity\Application\ManageUsers\ResetUserByUsernameHandler;
use App\Shared\Domain\Exception\DomainError;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:reset',
    description: 'Reset a user (for example a locked-out admin): new password and TOTP through a new activation link (PD-35).',
)]
final readonly class ResetUserConsoleCommand
{
    public function __construct(
        private ResetUserByUsernameHandler $resetUser,
        private ActivationLinkUrl $links,
    ) {
    }

    /**
     * Exit code: 0 when the user was reset, 1 when the user is unknown or cannot be reset.
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Username of the user to reset.')]
        string $username,
    ): int {
        try {
            $link = ($this->resetUser)(new ResetUserByUsernameCommand($username));
        } catch (DomainError $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('User "%s" was reset. Their sessions have ended.', $link->username));
        $io->writeln(\sprintf(
            'Activation link (valid until %s UTC, shown only once):',
            $link->activationExpiresAt->format('Y-m-d H:i'),
        ));
        $io->writeln($this->links->for($link->activationToken));

        return Command::SUCCESS;
    }
}
