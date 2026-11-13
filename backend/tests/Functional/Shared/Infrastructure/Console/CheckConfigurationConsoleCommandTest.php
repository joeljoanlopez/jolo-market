<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Infrastructure\Console;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Wiring only: the rules of each check are covered by their unit tests. The test environment is valid.
 */
final class CheckConfigurationConsoleCommandTest extends KernelTestCase
{
    #[Test]
    public function it_runs_the_checks_of_every_context_against_the_environment(): void
    {
        self::bootKernel();
        $command = new CommandTester(new Application(self::$kernel ?? throw new \LogicException())->find('app:config:check'));

        $exitCode = $command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode, $command->getDisplay());
        self::assertStringContainsString('The configuration is valid.', $command->getDisplay());
    }
}
