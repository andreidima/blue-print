<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Enums\StatusComanda;
use App\Enums\TipComanda;
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
            ]);
        }

        $cereriOfertaDeschise = Comanda::where('tip', TipComanda::CerereOferta->value)
            ->whereNotIn('status', StatusComanda::finalStates())
            ->count();

        $comenziIntarziate = Comanda::overdue()->count();

        $comenziInExecutie = Comanda::where('status', StatusComanda::InExecutie->value)->count();

        return view('home', compact('cereriOfertaDeschise', 'comenziIntarziate', 'comenziInExecutie'));
    }
}
