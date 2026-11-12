<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Scheduler;

use App\Catalog\Infrastructure\Scheduler\CatalogSchedule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Trigger\CallbackTrigger;

final class CatalogScheduleTest extends TestCase
{
    private const string INGEST = 'app:catalog:ingest';
    private const string PRUNE = 'app:catalog:prune-history';

    #[Test]
    public function it_runs_the_ingestion_command_without_failing_the_worker(): void
    {
        $payload = self::payload($this->recurringMessage(self::INGEST));

        self::assertSame(self::INGEST, $payload->input);
        // A failed run is already recorded and logged; throwing would only make Messenger retry it.
        self::assertFalse($payload->throwOnFailure);
    }

    #[Test]
    public function it_runs_the_ingestion_at_the_configured_time_in_madrid(): void
    {
        $next = $this->nextRun($this->recurringMessage(self::INGEST));

        self::assertSame('2026-11-09 05:00 Europe/Madrid', $next);
    }

    #[Test]
    public function it_follows_the_configured_ingestion_expression(): void
    {
        $next = $this->nextRun($this->recurringMessage(self::INGEST, ingestion: '30 17 * * 1'));

        self::assertSame('2026-11-09 17:30 Europe/Madrid', $next);
    }

    #[Test]
    public function it_runs_the_prune_command(): void
    {
        $payload = self::payload($this->recurringMessage(self::PRUNE));

        self::assertSame(self::PRUNE, $payload->input);
        self::assertFalse($payload->throwOnFailure);
    }

    #[Test]
    public function it_prunes_at_the_configured_time_in_madrid(): void
    {
        $next = $this->nextRun($this->recurringMessage(self::PRUNE, prune: '15 3 * * *'));

        self::assertSame('2026-11-09 03:15 Europe/Madrid', $next);
    }

    private function recurringMessage(string $command, string $ingestion = '0 5 * * *', string $prune = '30 4 * * *'): RecurringMessage
    {
        $messages = new CatalogSchedule($ingestion, $prune, new ArrayAdapter())->getSchedule()->getRecurringMessages();
        self::assertCount(2, $messages);

        foreach ($messages as $message) {
            if (self::payload($message)->input === $command) {
                return $message;
            }
        }

        self::fail(\sprintf('No recurring message runs "%s".', $command));
    }

    private function nextRun(RecurringMessage $message): ?string
    {
        return $message->getTrigger()
            ->getNextRunDate(new \DateTimeImmutable('2026-11-08 12:00:00', new \DateTimeZone('UTC')))
            ?->format('Y-m-d H:i e');
    }

    private static function payload(RecurringMessage $message): RunCommandMessage
    {
        $now = new \DateTimeImmutable('2026-11-09 04:00:00', new \DateTimeZone('UTC'));
        $context = new MessageContext('default', 'id', new CallbackTrigger(static fn () => $now), $now);
        $payload = iterator_to_array($message->getMessages($context), false)[0] ?? null;
        self::assertInstanceOf(RunCommandMessage::class, $payload);

        return $payload;
    }
}
