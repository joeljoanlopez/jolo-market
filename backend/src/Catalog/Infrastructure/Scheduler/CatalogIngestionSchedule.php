<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Runs app:catalog:ingest on INGESTION_SCHEDULE (PD-27). The "scheduler" container consumes it
 * with `messenger:consume scheduler_default`.
 */
#[AsSchedule('default')]
final readonly class CatalogIngestionSchedule implements ScheduleProviderInterface
{
    private const string TIMEZONE = 'Europe/Madrid';

    public function __construct(
        #[Autowire(env: 'INGESTION_SCHEDULE')]
        private string $expression,
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        // A failed run is already recorded and logged. Throwing would only make Messenger retry the whole ingestion.
        $ingest = new RunCommandMessage('app:catalog:ingest', throwOnFailure: false);

        return new Schedule()
            ->add(RecurringMessage::cron($this->expression, $ingest, new \DateTimeZone(self::TIMEZONE)))
            // If the worker was down at the scheduled time, run once when it is back (not once per missed run).
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
