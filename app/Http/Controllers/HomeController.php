<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use App\Enums\StatusComanda;
use App\Enums\TipComanda;
use App\Models\ComandaEtapaUser;
use App\Models\Comanda;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'stat_from' => ['nullable', 'date'],
            'stat_to' => ['nullable', 'date'],
        ]);

        $defaultFrom = now()->subDays(30)->toDateString();
        $defaultTo = now()->toDateString();
        $statFrom = $data['stat_from'] ?? $defaultFrom;
        $statTo = $data['stat_to'] ?? $defaultTo;

        $fromDate = Carbon::parse($statFrom)->startOfDay();
        $toDate = Carbon::parse($statTo)->endOfDay();
        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
            $statFrom = $fromDate->toDateString();
            $statTo = $toDate->toDateString();
        }

        $statsInterval = [
            'comenzi_total' => 0,
            'cereri_total' => 0,
            'comenzi_finalizate' => 0,
            'cereri_finalizate' => 0,
            'comenzi_intarziate' => 0,
            'cereri_intarziate' => 0,
        ];

        if (!Schema::hasTable('comenzi')) {
            return view('home', [
                'cereriOfertaDeschise' => 0,
                'comenziIntarziate' => 0,
                'comenziInExecutie' => 0,
                'comenziActive' => 0,
                'cereriInAsteptareTotal' => 0,
                'cereriInAsteptareMele' => 0,
                'statFrom' => $statFrom,
                'statTo' => $statTo,
                'statsInterval' => $statsInterval,
            ]);
        }

        $cereriOfertaDeschise = Comanda::where('tip', TipComanda::CerereOferta->value)
            ->whereNotIn('status', StatusComanda::finalStates())
            ->count();

        $comenziIntarziate = Comanda::overdue()->count();

        $comenziInExecutie = Comanda::where('status', StatusComanda::InExecutie->value)->count();

        $comenziActive = Comanda::whereNotIn('status', StatusComanda::finalStates())->count();

        $cereriInAsteptareTotal = Comanda::whereHas('etapaAssignments', function ($query) {
            $query->where('status', ComandaEtapaUser::STATUS_PENDING);
        })->count();

        $userId = auth()->id();
        $cereriInAsteptareMele = Comanda::whereHas('etapaAssignments', function ($query) use ($userId) {
            $query->where('status', ComandaEtapaUser::STATUS_PENDING)
                ->where('user_id', $userId);
        })->count();

        $createdRange = Comanda::whereBetween('data_solicitarii', [$fromDate, $toDate]);
        $statsInterval['comenzi_total'] = (clone $createdRange)
            ->where('tip', TipComanda::ComandaFerma->value)
            ->count();
        $statsInterval['cereri_total'] = (clone $createdRange)
            ->where('tip', TipComanda::CerereOferta->value)
            ->count();

        $finalizateRange = Comanda::whereNotNull('finalizat_la')
            ->whereBetween('finalizat_la', [$fromDate, $toDate]);
        $statsInterval['comenzi_finalizate'] = (clone $finalizateRange)
            ->where('tip', TipComanda::ComandaFerma->value)
            ->count();
        $statsInterval['cereri_finalizate'] = (clone $finalizateRange)
            ->where('tip', TipComanda::CerereOferta->value)
            ->count();

        $intarziateRange = Comanda::whereNotNull('finalizat_la')
            ->whereNotNull('timp_estimat_livrare')
            ->whereBetween('finalizat_la', [$fromDate, $toDate])
            ->whereColumn('finalizat_la', '>', 'timp_estimat_livrare');
        $statsInterval['comenzi_intarziate'] = (clone $intarziateRange)
            ->where('tip', TipComanda::ComandaFerma->value)
            ->count();
        $statsInterval['cereri_intarziate'] = (clone $intarziateRange)
            ->where('tip', TipComanda::CerereOferta->value)
            ->count();

        return view('home', compact(
            'cereriOfertaDeschise',
            'comenziIntarziate',
            'comenziInExecutie',
            'comenziActive',
            'cereriInAsteptareTotal',
            'cereriInAsteptareMele',
            'statFrom',
            'statTo',
            'statsInterval',
        ));
    }
}
