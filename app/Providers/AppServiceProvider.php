<?php

namespace App\Providers;

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

            $currentUserId = auth()->id();
            if (!$currentUserId) {
                return;
            }

            $cereriOfertaDeschise = Comanda::query()
                ->operationallyOpen()
                ->where('tip', TipComanda::CerereOferta->value)
                ->count();

            $notificariComenziIntarziate = Comanda::query()
                ->overdue()
                ->where('tip', TipComanda::ComandaFerma->value)
                ->count();
            $notificariComenziSoon = Comanda::dueSoon()->count();
            $notificariComenziAsignateMie = 0;
            $notificariCereriAsteptareMele = 0;

            if (Schema::hasTable('comanda_etapa_user')) {
                $notificariComenziAsignateMie = Comanda::assignedTo($currentUserId)
                    ->operationallyOpen()
                    ->count();

                $notificariCereriAsteptareMele = Comanda::query()
                    ->operationallyOpen()
                    ->whereHas('etapaAssignments', function ($query) use ($currentUserId) {
                        $query->where('user_id', $currentUserId)
                            ->where('status', ComandaEtapaUser::STATUS_PENDING);
                    })
                    ->count();
            }

            $counts = [
                'cereriOfertaDeschise' => $cereriOfertaDeschise,
                'notificariComenziIntarziate' => $notificariComenziIntarziate,
                'notificariComenziSoon' => $notificariComenziSoon,
                'notificariComenziAsignateMie' => $notificariComenziAsignateMie,
                'notificariCereriAsteptareMele' => $notificariCereriAsteptareMele,
                'notificariTotal' => $notificariComenziIntarziate
                    + $notificariComenziSoon
                    + $notificariComenziAsignateMie
                    + $notificariCereriAsteptareMele
                    + $cereriOfertaDeschise,
            ];

            $view->with($counts);
        });
    }
}
