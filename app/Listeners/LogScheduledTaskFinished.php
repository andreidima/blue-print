<?php

namespace App\Listeners;

use App\Listeners\Concerns\ResolvesScheduledTaskName;
use App\Models\CronJobLog;
use Illuminate\Console\Events\ScheduledTaskFinished;

class LogScheduledTaskFinished
{
    use ResolvesScheduledTaskName;

    public function handle(ScheduledTaskFinished $event): void
    {
        CronJobLog::create([
            'job_name' => $this->resolveJobName($event->task),
            'ran_at' => now(),
            'status' => 'success',
            'details' => $this->buildDetails($event),
        ]);
    }

    private function buildDetails(ScheduledTaskFinished $event): string
    {
        $details = ['Completed in ' . number_format($event->runtime ?? 0, 2) . ' seconds.'];

        $result = $event->result ?? null;

        if (is_string($result) && trim($result) !== '') {
            $details[] = 'Result: ' . trim($result);
        } elseif (is_int($result) && $result !== 0) {
            $details[] = 'Exit code: ' . $result;
        }

        return implode(' ', $details);
    }
}
