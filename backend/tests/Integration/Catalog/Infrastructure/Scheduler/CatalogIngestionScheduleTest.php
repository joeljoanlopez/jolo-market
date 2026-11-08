<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Scheduler;

use App\Catalog\Infrastructure\Scheduler\CatalogIngestionSchedule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

final class CatalogIngestionScheduleTest extends TestCase
{
    #[Test]
    public function it_runs_the_ingestion_command_without_failing_the_worker(): void
    {
        $message = $this->recurringMessage('0 5 * * *');

        $payload = iterator_to_array($message->getMessages(self::context()), false)[0] ?? null;
        self::assertInstanceOf(RunCommandMessage::class, $payload);
        self::assertSame('app:catalog:ingest', $payload->input);
        // A failed run is already recorded and logged; throwing would only make Messenger retry it.
        self::assertFalse($payload->throwOnFailure);
    }

    #[Test]
    public function it_runs_at_the_configured_time_in_madrid(): void
    {
        $trigger = $this->recurringMessage('0 5 * * *')->getTrigger();

        $next = $trigger->getNextRunDate(new \DateTimeImmutable('2026-11-08 12:00:00', new \DateTimeZone('UTC')));

        self::assertNotNull($next);
        self::assertSame('2026-11-09 05:00 Europe/Madrid', $next->format('Y-m-d H:i e'));
    }

    #[Test]
    public function it_follows_the_configured_expression(): void
    {
        $trigger = $this->recurringMessage('30 17 * * 1')->getTrigger();

        $next = $trigger->getNextRunDate(new \DateTimeImmutable('2026-11-08 12:00:00', new \DateTimeZone('UTC')));

        self::assertSame('2026-11-09 17:30', $next?->format('Y-m-d H:i'));
    }

    private function recurringMessage(string $expression): RecurringMessage
    {
        $messages = new CatalogIngestionSchedule($expression, new ArrayAdapter())->getSchedule()->getRecurringMessages();
        self::assertCount(1, $messages);

        return $messages[0];
    }

    private static function context(): MessageContext
    {
        $now = new \DateTimeImmutable('2026-11-09 04:00:00', new \DateTimeZone('UTC'));

        return new MessageContext('default', 'id', new \Symfony\Component\Scheduler\Trigger\CallbackTrigger(static fn () => $now), $now);
    }
}
