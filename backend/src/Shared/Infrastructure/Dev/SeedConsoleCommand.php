<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Dev;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * PD-57: loads the development data. It runs the commands of each context by name, so no
 * context imports another. Every step is safe to repeat: existing users are never touched and
 * the backfill skips the days that already have a run.
 */
#[AsCommand(
    name: 'app:dev:seed',
    description: 'Development only: create the dummy users that are missing and backfill 180 days of dummy price history.',
)]
final class SeedConsoleCommand extends Command
{
    /** Lists first: they refer to users and products. */
    private const array RESET_COMMANDS = ['app:dev:lists:reset', 'app:dev:catalog:reset', 'app:dev:users:reset'];

    private const array SEED_COMMANDS = ['app:dev:users', 'app:dev:catalog:backfill'];

    protected function configure(): void
    {
        $this
            ->addOption('reset', null, InputOption::VALUE_NONE, 'First delete every product, price snapshot, ingestion run, user and shopping list.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'With --reset, do not ask for confirmation.');
    }

    /**
     * Exit code: 0 when every step succeeded, 1 when the reset was not confirmed or a step failed.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $commands = self::SEED_COMMANDS;

        if (true === $input->getOption('reset')) {
            $whatIsDeleted = 'every product, price snapshot, ingestion run, user (ending every session) and shopping list';
            if (!ResetConfirmation::granted($input, $io, true === $input->getOption('force'), $whatIsDeleted)) {
                return Command::FAILURE;
            }
            $commands = [...self::RESET_COMMANDS, ...self::SEED_COMMANDS];
        }

        foreach ($commands as $name) {
            $io->section($name);
            $arguments = \in_array($name, self::RESET_COMMANDS, true) ? ['--force' => true] : [];
            $step = new ArrayInput($arguments);
            $step->setInteractive(false);
            if (Command::SUCCESS !== $this->application()->find($name)->run($step, $output)) {
                $io->error(\sprintf('%s failed: the development data is incomplete.', $name));

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function application(): \Symfony\Component\Console\Application
    {
        return $this->getApplication() ?? throw new \LogicException('app:dev:seed runs other commands, so it needs the console application.');
    }
}
