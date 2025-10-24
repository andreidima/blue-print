<?php

namespace App\Providers;

use App\Services\WooCommerce\Client;
use App\Services\WooCommerce\OrderFulfillmentService;
use App\Services\WooCommerce\OrderStatusService;
use App\Services\WooCommerce\OrderSynchronizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Route;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            return Client::fromConfig();
        });

        $this->app->singleton(OrderFulfillmentService::class, fn () => new OrderFulfillmentService());

        $this->app->singleton(OrderSynchronizer::class, function ($app) {
            return new OrderSynchronizer($app->make(OrderFulfillmentService::class));
        });

        $this->app->singleton(OrderStatusService::class, function ($app) {
            return new OrderStatusService(
                $app->make(Client::class),
                $app->make(OrderSynchronizer::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::resourceVerbs([
            'create' => 'adauga',
            'edit' => 'modifica'
        ]);
        Paginator::useBootstrap();
        Model::preventLazyLoading();
    }
}
