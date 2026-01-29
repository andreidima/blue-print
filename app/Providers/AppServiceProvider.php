<?php

namespace App\Providers;

use App\Enums\StatusComanda;
use App\Enums\TipComanda;
use App\Models\Comanda;
use App\Models\ComandaEtapaUser;
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

            $notificariComenziIntarziate = Comanda::overdue()->count();
            $notificariComenziSoon = Comanda::dueSoon()->count();
            $notificariCereriAsteptareMele = 0;
            if (Schema::hasTable('comanda_etapa_user')) {
                $notificariCereriAsteptareMele = Comanda::whereHas('etapaAssignments', function ($query) {
                    $query->where('user_id', auth()->id())
                        ->where('status', ComandaEtapaUser::STATUS_PENDING);
                })->count();
            }

            $notificariTotal = $notificariComenziIntarziate
                + $notificariComenziSoon
                + $notificariCereriAsteptareMele
                + $cereriOfertaDeschise;

            $view->with([
                'cereriOfertaDeschise' => $cereriOfertaDeschise,
                'notificariComenziIntarziate' => $notificariComenziIntarziate,
                'notificariComenziSoon' => $notificariComenziSoon,
                'notificariCereriAsteptareMele' => $notificariCereriAsteptareMele,
                'notificariTotal' => $notificariTotal,
            ]);
        });
    }
}
