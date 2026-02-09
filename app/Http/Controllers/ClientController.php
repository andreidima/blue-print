<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Rules\CnpRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkUserPermission:clienti.write')->only(['store', 'update', 'destroy', 'quickStore']);
    }

    private function buildClientLabel(Client $client): string
    {
        return $client->nume_complet;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->session()->forget('returnUrl');

        $search = $request->search;

        $clienti = Client::when($search, function ($query, $search) {
            $query->where('nume', 'like', '%' . $search . '%')
                ->orWhere('telefon', 'like', '%' . $search . '%')
                ->orWhere('telefon_secundar', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%');
        })
            ->orderBy('nume')
            ->simplePaginate(25);

        return view('clienti.index', compact('clienti', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('clienti.save');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $type = $request->input('type') ?: 'pf';

        $data = $request->validate([
            'type' => ['nullable', Rule::in(['pf', 'pj'])],
            'nume' => ['required', 'string', 'max:100'],
            'adresa' => ['nullable', 'string', 'max:255'],
            'telefon' => ['nullable', 'string', 'max:50'],
            'telefon_secundar' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'cnp' => ['nullable', 'string', 'max:13', new CnpRule($type === 'pf')],
            'sex' => ['nullable', 'string', 'max:1', Rule::in(['M', 'F'])],
            'reg_com' => ['nullable', 'string', 'max:50'],
            'cui' => ['nullable', 'string', 'max:20'],
            'iban' => ['nullable', 'string', 'max:50'],
            'banca' => ['nullable', 'string', 'max:100'],
            'reprezentant' => ['nullable', 'string', 'max:150'],
            'reprezentant_functie' => ['nullable', 'string', 'max:150'],
        ]);

        $data['type'] = $data['type'] ?? 'pf';

        $client = Client::create($data);

        return redirect($request->session()->get('returnUrl', route('clienti.index')))
            ->with('success', 'Clientul <strong>' . e($client->nume_complet) . '</strong> a fost adaugat cu succes!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Client $client)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('clienti.show', compact('client'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Client $client)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('clienti.save', compact('client'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        $type = $request->input('type') ?: ($client->type ?: 'pf');

        $data = $request->validate([
            'type' => ['nullable', Rule::in(['pf', 'pj'])],
            'nume' => ['required', 'string', 'max:100'],
            'adresa' => ['nullable', 'string', 'max:255'],
            'telefon' => ['nullable', 'string', 'max:50'],
            'telefon_secundar' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'cnp' => ['nullable', 'string', 'max:13', new CnpRule($type === 'pf')],
            'sex' => ['nullable', 'string', 'max:1', Rule::in(['M', 'F'])],
            'reg_com' => ['nullable', 'string', 'max:50'],
            'cui' => ['nullable', 'string', 'max:20'],
            'iban' => ['nullable', 'string', 'max:50'],
            'banca' => ['nullable', 'string', 'max:100'],
            'reprezentant' => ['nullable', 'string', 'max:150'],
            'reprezentant_functie' => ['nullable', 'string', 'max:150'],
        ]);

        $data['type'] = $data['type'] ?? 'pf';

        $client->update($data);

        return redirect($request->session()->get('returnUrl', route('clienti.index')))
            ->with('status', 'Clientul <strong>' . e($client->nume_complet) . '</strong> a fost modificat cu succes!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $client->delete();

        return back()->with('status', 'Clientul a fost sters cu succes!');
    }

    public function selectOptions(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'id' => ['nullable', 'integer', 'exists:clienti,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $limit = $data['limit'] ?? 25;
        $page = $data['page'] ?? 1;

        if (!empty($data['id'])) {
            $client = Client::findOrFail($data['id']);

            return response()->json([
                'results' => [[
                    'id' => $client->id,
                    'label' => $this->buildClientLabel($client),
                ]],
            ]);
        }

        $search = $data['search'] ?? null;
        $digits = $search ? preg_replace('/\D+/', '', $search) : '';

        $paginator = Client::query()
            ->when($search, function ($query) use ($search, $digits) {
                $query->where(function ($query) use ($search, $digits) {
                    $query->where('nume', 'like', '%' . $search . '%');

                    if ($digits !== '') {
                        $query->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefon, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') like ?",
                            ['%' . $digits . '%']
                        );
                        $query->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefon_secundar, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') like ?",
                            ['%' . $digits . '%']
                        );
                    }
                });
            })
            ->orderBy('nume')
            ->simplePaginate($limit, ['*'], 'page', $page);

        return response()->json([
            'results' => $paginator->getCollection()->map(fn ($client) => [
                'id' => $client->id,
                'label' => $this->buildClientLabel($client),
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
        $type = $request->input('type') ?: 'pf';

        $data = $request->validate([
            'type' => ['nullable', Rule::in(['pf', 'pj'])],
            'nume' => ['required', 'string', 'max:100'],
            'adresa' => ['nullable', 'string', 'max:255'],
            'telefon' => ['nullable', 'string', 'max:50'],
            'telefon_secundar' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'cnp' => ['nullable', 'string', 'max:13', new CnpRule($type === 'pf')],
            'sex' => ['nullable', 'string', 'max:1', Rule::in(['M', 'F'])],
            'reg_com' => ['nullable', 'string', 'max:50'],
            'cui' => ['nullable', 'string', 'max:20'],
            'iban' => ['nullable', 'string', 'max:50'],
            'banca' => ['nullable', 'string', 'max:100'],
            'reprezentant' => ['nullable', 'string', 'max:150'],
            'reprezentant_functie' => ['nullable', 'string', 'max:150'],
        ]);

        $data['type'] = $data['type'] ?? 'pf';

        $client = Client::create($data);

        return response()->json([
            'client' => [
                'id' => $client->id,
                'label' => $this->buildClientLabel($client),
            ],
        ], 201);
    }
}
