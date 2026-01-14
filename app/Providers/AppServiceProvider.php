<?php

namespace App\Providers;

use App\Enums\StatusComanda;
use App\Enums\TipComanda;
use App\Models\Comanda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Route;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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

        View::composer('layouts.app', function ($view) {
            if (!auth()->check()) {
                return;
            }
            if (!Schema::hasTable('comenzi')) {
                return;
            }

            $cereriOfertaDeschise = Comanda::where('tip', TipComanda::CerereOferta->value)
                ->whereNotIn('status', StatusComanda::finalStates())
                ->count();

            $view->with('cereriOfertaDeschise', $cereriOfertaDeschise);
        });
    }
}
