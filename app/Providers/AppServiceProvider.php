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
            $notificariComenziAsignateMie = 0;
            $notificariCereriAsteptareMele = 0;
            $currentUserId = auth()->id();
            if (Schema::hasTable('comanda_etapa_user')) {
                if ($currentUserId) {
                    $notificariComenziAsignateMie = Comanda::assignedTo($currentUserId)
                        ->whereNotIn('status', StatusComanda::finalStates())
                        ->count();
                }

                $notificariCereriAsteptareMele = Comanda::whereHas('etapaAssignments', function ($query) {
                    $query->where('user_id', auth()->id())
                        ->where('status', ComandaEtapaUser::STATUS_PENDING);
                })->count();
            }

            $notificariTotal = $notificariComenziIntarziate
                + $notificariComenziSoon
                + $notificariComenziAsignateMie
                + $notificariCereriAsteptareMele
                + $cereriOfertaDeschise;

            $view->with([
                'cereriOfertaDeschise' => $cereriOfertaDeschise,
                'notificariComenziIntarziate' => $notificariComenziIntarziate,
                'notificariComenziSoon' => $notificariComenziSoon,
                'notificariComenziAsignateMie' => $notificariComenziAsignateMie,
                'notificariCereriAsteptareMele' => $notificariCereriAsteptareMele,
                'notificariTotal' => $notificariTotal,
            ]);
        });
    }
}
