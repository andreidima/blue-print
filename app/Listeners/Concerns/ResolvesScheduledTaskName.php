<?php

namespace App\Listeners\Concerns;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;

trait ResolvesScheduledTaskName
{
    protected function resolveJobName(ScheduledEvent $event): string
    {
        $summary = trim($event->getSummaryForDisplay());

        if ($summary !== '') {
            return $summary;
        }

        if (property_exists($event, 'command') && is_string($event->command) && trim($event->command) !== '') {
            return trim($event->command);
        }

        if (property_exists($event, 'description') && is_string($event->description) && trim($event->description) !== '') {
            return trim($event->description);
        }

        return 'Scheduled task';
    }
}
