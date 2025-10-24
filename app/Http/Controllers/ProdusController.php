<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Produs;
use App\Models\Categorie;
use App\Http\Requests\ProdusRequest;

class ProdusController extends Controller
{
    /** Display a listing of the resource. */
    public function index(Request $request)
    {
        // clear previous return URL
        $request->session()->forget('returnUrl');

        // filters
        $searchNume       = trim($request->searchNume);
        $searchCategorie  = $request->searchCategorie;

        // sorting
        $sort = $request->query('sort');
        $direction = strtolower($request->query('direction', 'asc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        // all categories for filter dropdown
        $allCategorii = Categorie::select('id','nume')->orderBy('nume')->get();

        // build query
        $produseQuery = Produs::with('categorie')
            ->when($searchNume, function($q) use($searchNume) {
                $q->where('nume', 'LIKE', "%{$searchNume}%");
            })
            ->when($searchCategorie, function($q) use($searchCategorie) {
                $q->where('categorie_id', $searchCategorie);
            });

        if ($sort === 'name') {
            $produseQuery->orderBy('nume', $direction)->orderBy('id');
        } else {
            $produseQuery->latest();
        }

        $produse = $produseQuery->simplePaginate(25);

        return view('produse.index', compact(
            'produse','allCategorii','searchNume','searchCategorie','sort','direction'
        ));
    }

    /** Show the form for creating a new resource. */
    public function create(Request $request)
    {
        // remember where to go back
        $request->session()->get('returnUrl') ?:
            $request->session()->put('returnUrl', url()->previous());

        $allCategorii = Categorie::select('id','nume')->orderBy('nume')->get();

        return view('produse.save', compact('allCategorii'));
    }

    /** Store a newly created resource in storage. */
    public function store(ProdusRequest $request)
    {
        $data = $request->validated();
        $produs = Produs::create($data);

        return redirect(
            $request->session()->get('returnUrl', route('produse.index'))
        )->with('status', "Produs <strong>" . e($produs->nume) . "</strong> a fost adăugat cu succes!");
    }

    /** Display the specified resource. */
    public function show(Request $request, Produs $produs)
    {
        $request->session()->get('returnUrl') ?:
            $request->session()->put('returnUrl', url()->previous());

        return view('produse.show', compact('produs'));
    }

    /** Show the form for editing the specified resource. */
    public function edit(Request $request, Produs $produs)
    {
        $request->session()->get('returnUrl') ?:
            $request->session()->put('returnUrl', url()->previous());

        $allCategorii = Categorie::select('id','nume')->orderBy('nume')->get();

        return view('produse.save', compact('produs','allCategorii'));
    }

    /** Update the specified resource in storage. */
    public function update(ProdusRequest $request, Produs $produs)
    {
        $data = $request->validated();
        $produs->update($data);

        return redirect(
            $request->session()->get('returnUrl', route('produse.index'))
        )->with('status', "Produs <strong>" . e($produs->nume) . "</strong> a fost modificat cu succes!");
    }

    /** Remove the specified resource from storage. */
    public function destroy(Request $request, Produs $produs)
    {
        $produs->delete();

        return back()->with('status', "Produs <strong>" . e($produs->nume) . "</strong> a fost șters cu succes!");
    }

    /**
     * Show a print-optimized label (QR + name) for a single product.
     *
     * @param  \App\Models\Produs  $produs
     * @return \Illuminate\View\View
     */
    public function eticheta(Produs $produs)
    {
        return view('produse.eticheta', compact('produs'));
    }
}
