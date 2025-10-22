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
            ->leftJoin('wc_order_items', 'wc_order_items.id', '=', 'miscari_stoc.wc_order_item_id')
            ->select([
                'miscari_stoc.nr_comanda',
                DB::raw('MIN(miscari_stoc.created_at) AS data_inceput'),
                DB::raw('MAX(miscari_stoc.created_at) AS data_sfarsit'),
                DB::raw('SUM(CASE WHEN miscari_stoc.wc_order_item_id IS NOT NULL THEN ABS(miscari_stoc.delta) ELSE 0 END) AS fulfilled_qty'),
                DB::raw('SUM(CASE WHEN miscari_stoc.wc_order_item_id IS NOT NULL THEN wc_order_items.quantity ELSE 0 END) AS ordered_qty'),
            ])
            ->where('miscari_stoc.delta', '<', 0)

            // Exclude records without a command number:
            ->whereNotNull('miscari_stoc.nr_comanda')
            ->where('miscari_stoc.nr_comanda', '<>', '')

            ->when($search, function($q, $search) {
                $q->where('miscari_stoc.nr_comanda', 'LIKE', $search);
            })
            ->groupBy('miscari_stoc.nr_comanda');

        if ($sort === 'number') {
            $query->orderByRaw("CAST(miscari_stoc.nr_comanda AS UNSIGNED) {$direction}")
                ->orderBy('data_inceput', 'desc');
        } else {
            $query->orderBy('data_inceput', 'desc');
        }

        $comenzi = $query->paginate(25)->withQueryString();

        $comenzi->getCollection()->transform(function ($row) {
            $row->fulfilled_qty = (int) ($row->fulfilled_qty ?? 0);
            $row->ordered_qty = (int) ($row->ordered_qty ?? 0);
            $row->fulfillment = $this->summarizeFulfillment($row->fulfilled_qty, $row->ordered_qty);

            return $row;
        });

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

        $movements = MiscareStoc::with(['produs','user', 'orderItem'])
            ->where('nr_comanda', $nr_comanda)
            ->where('delta', '<', 0)
            ->orderBy('created_at')
            ->get();

        // Calculăm prima și ultima dată
        $data_inceput = $movements->min('created_at');
        $data_sfarsit = $movements->max('created_at');

        $linkedOrderItems = $movements
            ->pluck('orderItem')
            ->filter();

        $ordered_qty = $linkedOrderItems
            ->unique('id')
            ->sum(fn ($item) => (int) ($item->quantity ?? 0));

        $fulfilled_qty = $movements
            ->filter(fn ($movement) => $movement->orderItem !== null)
            ->sum(fn ($movement) => abs((int) $movement->delta));

        $fulfillment = $this->summarizeFulfillment($fulfilled_qty, $ordered_qty);

        return view('comenzi-iesiri.show', [
            'nr_comanda'   => $nr_comanda,
            'movements'    => $movements,
            'data_inceput' => $data_inceput,
            'data_sfarsit' => $data_sfarsit,
            'fulfillment'  => $fulfillment,
            'fulfilled_qty'=> $fulfilled_qty,
            'ordered_qty'  => $ordered_qty,
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

    protected function summarizeFulfillment(int $fulfilled, int $ordered): array
    {
        if ($ordered <= 0) {
            return [
                'status' => null,
                'label' => 'Fără legătură',
                'badge' => 'bg-secondary',
            ];
        }

        if ($fulfilled >= $ordered) {
            return [
                'status' => 'fulfilled',
                'label' => 'Finalizat',
                'badge' => 'bg-success',
            ];
        }

        if ($fulfilled > 0) {
            return [
                'status' => 'partial',
                'label' => 'Parțial',
                'badge' => 'bg-warning text-dark',
            ];
        }

        return [
            'status' => 'pending',
            'label' => 'În așteptare',
            'badge' => 'bg-secondary',
        ];
    }
}

