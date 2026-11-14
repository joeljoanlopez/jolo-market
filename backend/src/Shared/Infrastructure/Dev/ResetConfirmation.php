<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Dev;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * PD-57: deleting development data asks first. Without interaction it is refused unless forced.
 */
final class ResetConfirmation
{
    public static function granted(InputInterface $input, SymfonyStyle $io, bool $force, string $whatIsDeleted): bool
    {
        if ($force) {
            return true;
        }

        if (!$input->isInteractive()) {
            $io->error(\sprintf('This deletes %s. Run it again with --force to confirm without interaction.', $whatIsDeleted));

            return false;
        }

        if (!$io->confirm(\sprintf('This deletes %s. Continue?', $whatIsDeleted), false)) {
            $io->warning('Nothing was deleted.');

            return false;
        }

        return true;
    }
}
