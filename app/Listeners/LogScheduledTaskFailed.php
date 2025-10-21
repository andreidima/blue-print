<?php

namespace App\Listeners;

use App\Listeners\Concerns\ResolvesScheduledTaskName;
use App\Models\CronJobLog;
use Illuminate\Console\Events\ScheduledTaskFailed;

class LogScheduledTaskFailed
{
    use ResolvesScheduledTaskName;

    public function handle(ScheduledTaskFailed $event): void
    {
        CronJobLog::create([
            'job_name' => $this->resolveJobName($event->task),
            'ran_at' => now(),
            'status' => 'failed',
            'details' => $this->buildDetails($event),
        ]);
    }

    private function buildDetails(ScheduledTaskFailed $event): string
    {
        $parts = [];

        if (!is_null($event->runtime)) {
            $parts[] = 'Failed after ' . number_format($event->runtime, 2) . ' seconds.';
        } else {
            $parts[] = 'Failed.';
        }

        $message = trim($event->exception->getMessage());
        $exceptionClass = get_class($event->exception);
        $parts[] = sprintf('%s: %s', $exceptionClass, $message !== '' ? $message : 'No exception message provided.');

        return implode(' ', $parts);
    }
}
