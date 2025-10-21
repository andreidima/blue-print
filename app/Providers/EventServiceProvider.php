<?php

namespace App\Providers;

use App\Listeners\LogScheduledTaskFailed;
use App\Listeners\LogScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ScheduledTaskFinished::class => [
            LogScheduledTaskFinished::class,
        ],
        ScheduledTaskFailed::class => [
            LogScheduledTaskFailed::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
