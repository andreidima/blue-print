<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
    public function index()
    {
        if (!Schema::hasTable('comenzi')) {
            return view('home', [
                'cereriOfertaDeschise' => 0,
                'comenziIntarziate' => 0,
                'comenziInExecutie' => 0,
                'comenziActive' => 0,
                'cereriInAsteptareTotal' => 0,
                'cereriInAsteptareMele' => 0,
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

        return view('home', compact(
            'cereriOfertaDeschise',
            'comenziIntarziate',
            'comenziInExecutie',
            'comenziActive',
            'cereriInAsteptareTotal',
            'cereriInAsteptareMele',
        ));
    }
}
