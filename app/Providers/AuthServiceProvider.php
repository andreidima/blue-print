<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user) {
            return $user->isSuperAdmin() ? true : null;
        });

        Gate::define('admin-action', function (User $user) {
            return $user->hasAnyRole(['Supervizor', 'Admin']);
        });

        Gate::define('super-admin-action', function (User $user) {
            return $user->hasAnyRole(['SuperAdmin']);
        });
    }
}
