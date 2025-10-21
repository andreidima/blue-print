<?php

namespace App\Http\Controllers;

use App\Models\MiscareStoc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF; // alias pentru Barryvdh\DomPDF\Facade\Pdf

class ComenziIesiriController extends Controller
{
    /**
     * Afișează lista de comenzi de ieșiri (distinct nr_comanda).
     */
    public function index(Request $request)
    {
        // clear previous return URL
        $request->session()->forget('returnUrl');

        $search = $request->query('searchNrComanda');
        $sort = $request->query('sort');
        $direction = strtolower($request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Construim query pentru ieșiri agregate pe nr_comanda
        $query = DB::table('miscari_stoc')
            ->select([
                'nr_comanda',
                DB::raw('MIN(created_at) AS data_inceput'),
                DB::raw('MAX(created_at) AS data_sfarsit'),
            ])
            ->where('delta', '<', 0)

            // Exclude records without a command number:
            ->whereNotNull('nr_comanda')
            ->where('nr_comanda', '<>', '')

            ->when($search, function($q, $search) {
                $q->where('nr_comanda', 'LIKE', $search);
            })
            ->groupBy('nr_comanda');

        if ($sort === 'number') {
            $query->orderByRaw("CAST(nr_comanda AS UNSIGNED) {$direction}")
                ->orderBy('data_inceput', 'desc');
        } else {
            $query->orderBy('data_inceput', 'desc');
        }

        $comenzi = $query->paginate(25)->withQueryString();

        return view('comenzi-iesiri.index', [
            'comenzi'           => $comenzi,
            'searchNrComanda'   => $search,
            'sort'              => $sort,
            'direction'         => $direction,
        ]);
    }

    /**
     * Afișează detaliul unei comenzi: toate ieșirile cu nr_comanda dat.
     */
    public function show(Request $request, string $nr_comanda)
    {
        // remember where to go back
        $request->session()->get('returnUrl') ?:
            $request->session()->put('returnUrl', url()->previous());

        $movements = MiscareStoc::with(['produs','user'])
            ->where('nr_comanda', $nr_comanda)
            ->where('delta', '<', 0)
            ->orderBy('created_at')
            ->get();

        // Calculăm prima și ultima dată
        $data_inceput = $movements->min('created_at');
        $data_sfarsit = $movements->max('created_at');

        return view('comenzi-iesiri.show', [
            'nr_comanda'   => $nr_comanda,
            'movements'    => $movements,
            'data_inceput' => $data_inceput,
            'data_sfarsit' => $data_sfarsit,
        ]);
    }

    /**
     * Generează și descarcă PDF-ul comenzii.
     */
    public function pdf(string $nr_comanda)
    {
        $movements = MiscareStoc::with(['produs','user'])
            ->where('nr_comanda', $nr_comanda)
            ->where('delta', '<', 0)
            ->orderBy('created_at')
            ->get();

        $data_inceput = $movements->min('created_at');
        $data_sfarsit = $movements->max('created_at');

        $pdf = PDF::loadView('comenzi-iesiri.pdf', [
            'nr_comanda'   => $nr_comanda,
            'movements'    => $movements,
            'data_inceput' => $data_inceput,
            'data_sfarsit' => $data_sfarsit,
        ]);

        return $pdf->download("comanda-{$nr_comanda}.pdf");
    }
}
