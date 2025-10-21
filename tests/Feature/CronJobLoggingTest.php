<?php

namespace Tests\Feature;

use App\Listeners\LogScheduledTaskFailed;
use App\Listeners\LogScheduledTaskFinished;
use App\Models\CronJobLog;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CronJobLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_successful_tasks(): void
    {
        $event = $this->createScheduledEvent();

        $listener = new LogScheduledTaskFinished();
        $listener->handle(new ScheduledTaskFinished($event, 1.234, 0));

        $this->assertDatabaseHas('cron_job_logs', [
            'job_name' => 'Test Cron Job',
            'status' => 'success',
        ]);

        $log = CronJobLog::first();
        $this->assertNotNull($log->ran_at);
        $this->assertStringContainsString('Completed in', $log->details);
    }

    public function test_it_logs_failed_tasks(): void
    {
        $event = $this->createScheduledEvent();

        $listener = new LogScheduledTaskFailed();
        $listener->handle(new ScheduledTaskFailed($event, new RuntimeException('Something went wrong'), 2.5));

        $this->assertDatabaseHas('cron_job_logs', [
            'job_name' => 'Test Cron Job',
            'status' => 'failed',
        ]);

        $log = CronJobLog::latest('id')->first();
        $this->assertStringContainsString('Something went wrong', $log->details);
    }

    private function createScheduledEvent(): ScheduledEvent
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:cron-job');
        $event->description('Test Cron Job');

        return $event;
    }
}
