<?php

namespace App\Http\Controllers;

use App\Models\Produs;
use Illuminate\Http\Request;

class ProdusController extends Controller
{
    private function buildProdusLabel(Produs $produs): string
    {
        return $produs->denumire . ' (' . number_format($produs->pret, 2) . ')';
    }

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

    public function selectOptions(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'id' => ['nullable', 'integer', 'exists:produse,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $limit = $data['limit'] ?? 25;
        $page = $data['page'] ?? 1;

        if (!empty($data['id'])) {
            $produs = Produs::findOrFail($data['id']);

            return response()->json([
                'results' => [[
                    'id' => $produs->id,
                    'label' => $this->buildProdusLabel($produs),
                ]],
            ]);
        }

        $search = $data['search'] ?? null;

        $paginator = Produs::query()
            ->where('activ', true)
            ->when($search, function ($query) use ($search) {
                $query->where('denumire', 'like', '%' . $search . '%');
            })
            ->orderBy('denumire')
            ->simplePaginate($limit, ['*'], 'page', $page);

        return response()->json([
            'results' => $paginator->getCollection()->map(fn ($produs) => [
                'id' => $produs->id,
                'label' => $this->buildProdusLabel($produs),
            ])->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'denumire' => ['required', 'string', 'max:150'],
            'pret' => ['required', 'numeric', 'min:0'],
            'activ' => ['nullable', 'boolean'],
        ]);

        $data['activ'] = array_key_exists('activ', $data) ? (bool) $data['activ'] : true;

        $produs = Produs::create($data);

        return response()->json([
            'produs' => [
                'id' => $produs->id,
                'label' => $this->buildProdusLabel($produs),
            ],
        ], 201);
    }
}
