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
 * Runs app:catalog:ingest on INGESTION_SCHEDULE (PD-27) and app:catalog:prune-history on
 * PRICE_HISTORY_PRUNE_SCHEDULE (PD-46). The "scheduler" container consumes them with
 * `messenger:consume scheduler_default`.
 */
#[AsSchedule('default')]
final readonly class CatalogSchedule implements ScheduleProviderInterface
{
    private const string TIMEZONE = 'Europe/Madrid';

    public function __construct(
        #[Autowire(env: 'INGESTION_SCHEDULE')]
        private string $ingestionExpression,
        #[Autowire(env: 'PRICE_HISTORY_PRUNE_SCHEDULE')]
        private string $pruneExpression,
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        // A failure is already logged (and a failed ingestion recorded). Throwing would only make Messenger retry the
        // whole command; the next scheduled run tries again anyway.
        $ingest = new RunCommandMessage('app:catalog:ingest', throwOnFailure: false);
        $prune = new RunCommandMessage('app:catalog:prune-history', throwOnFailure: false);

        return new Schedule()
            ->add(
                RecurringMessage::cron($this->ingestionExpression, $ingest, $timezone),
                RecurringMessage::cron($this->pruneExpression, $prune, $timezone),
            )
            // If the worker was down at the scheduled time, run once when it is back (not once per missed run).
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
