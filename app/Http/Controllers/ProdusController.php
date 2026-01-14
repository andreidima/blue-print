<?php

namespace App\Http\Controllers;

use App\Models\Produs;
use Illuminate\Http\Request;

class ProdusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->session()->forget('returnUrl');

        $search = $request->search;
        $activ = $request->activ;

        $produse = Produs::when($search, function ($query, $search) {
            $query->where('denumire', 'like', '%' . $search . '%');
        })
            ->when($activ !== null && $activ !== '', function ($query) use ($activ) {
                $query->where('activ', (bool) $activ);
            })
            ->orderBy('denumire')
            ->simplePaginate(25);

        return view('produse.index', compact('produse', 'search', 'activ'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('produse.save');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'denumire' => ['required', 'string', 'max:150'],
            'pret' => ['required', 'numeric', 'min:0'],
            'activ' => ['nullable', 'boolean'],
        ]);

        $data['activ'] = $request->boolean('activ');

        $produs = Produs::create($data);

        return redirect($request->session()->get('returnUrl', route('produse.index')))
            ->with('success', 'Produsul <strong>' . e($produs->denumire) . '</strong> a fost adaugat cu succes!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Produs $produs)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('produse.show', compact('produs'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Produs $produs)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('produse.save', compact('produs'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Produs $produs)
    {
        $data = $request->validate([
            'denumire' => ['required', 'string', 'max:150'],
            'pret' => ['required', 'numeric', 'min:0'],
            'activ' => ['nullable', 'boolean'],
        ]);

        $data['activ'] = $request->boolean('activ');

        $produs->update($data);

        return redirect($request->session()->get('returnUrl', route('produse.index')))
            ->with('status', 'Produsul <strong>' . e($produs->denumire) . '</strong> a fost modificat cu succes!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Produs $produs)
    {
        $produs->delete();

        return back()->with('status', 'Produsul a fost sters cu succes!');
    }
}
