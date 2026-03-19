<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientEmail;
use App\Rules\CnpRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkUserPermission:clienti.write')->only([
            'store',
            'update',
            'destroy',
            'bulkDestroy',
            'restore',
            'bulkRestore',
            'forceDelete',
            'bulkForceDelete',
            'quickStore',
        ]);
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
        return $this->listClienti($request, false);
    }

    public function trash(Request $request)
    {
        return $this->listClienti($request, true);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $this->rememberReturnUrl($request);

        return view('clienti.save');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateClientPayload($request);
        $emails = $this->extractEmailAddresses($data);
        $data['type'] = $data['type'] ?? 'pf';
        $data['email'] = $emails[0] ?? null;
        unset($data['emails']);

        $client = DB::transaction(function () use ($data, $emails) {
            $client = Client::create($data);
            $client->syncEmailAddresses($emails);

            return $client;
        });

        return redirect($request->session()->get('returnUrl', route('clienti.index')))
            ->with('success', 'Clientul <strong>' . e($client->nume_complet) . '</strong> a fost adaugat cu succes!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Client $client)
    {
        $this->rememberReturnUrl($request);

        return view('clienti.show', compact('client'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Client $client)
    {
        $this->rememberReturnUrl($request);

        return view('clienti.save', compact('client'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        $data = $this->validateClientPayload($request, $client);
        $emails = $this->extractEmailAddresses($data);
        $data['type'] = $data['type'] ?? 'pf';
        $data['email'] = $emails[0] ?? null;
        unset($data['emails']);

        DB::transaction(function () use ($client, $data, $emails) {
            $client->update($data);
            $client->syncEmailAddresses($emails);
        });

        return redirect($request->session()->get('returnUrl', route('clienti.index')))
            ->with('status', 'Clientul <strong>' . e($client->nume_complet) . '</strong> a fost modificat cu succes!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        if ($this->clientHasComenzi($client)) {
            return back()->with('warning', 'Clientul nu poate fi mutat in trash deoarece are comenzi asociate.');
        }

        $client->delete();

        return back()->with('status', 'Clientul a fost mutat in trash.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['required', 'integer', 'exists:clienti,id'],
        ]);

        $ids = collect($data['client_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors('Selecteaza cel putin un client.');
        }

        $clienti = Client::query()->whereIn('id', $ids)->get();
        $clientiCuComenzi = $clienti
            ->filter(fn (Client $client) => $this->clientHasComenzi($client))
            ->values();
        $clientIdsCuComenzi = $clientiCuComenzi->pluck('id')->all();
        $clientiDeSters = $clienti
            ->reject(fn (Client $client) => in_array($client->id, $clientIdsCuComenzi, true))
            ->pluck('id')
            ->all();

        $deletedCount = empty($clientiDeSters)
            ? 0
            : Client::query()->whereIn('id', $clientiDeSters)->delete();

        if ($deletedCount <= 0 && !empty($clientIdsCuComenzi)) {
            return back()->with('warning', 'Niciun client nu a fost mutat in trash. Clientii selectati au comenzi asociate.');
        }

        if ($deletedCount <= 0) {
            return back()->withErrors('Nu s-a putut muta niciun client in trash.');
        }

        $message = $deletedCount === 1
            ? 'Clientul selectat a fost mutat in trash.'
            : "Cei {$deletedCount} clienti selectati au fost mutati in trash.";

        $response = back()->with('status', $message);
        if (!empty($clientIdsCuComenzi)) {
            $response->with('warning', count($clientIdsCuComenzi) === 1
                ? 'Un client nu a fost mutat in trash deoarece are comenzi asociate.'
                : count($clientIdsCuComenzi) . ' clienti nu au fost mutati in trash deoarece au comenzi asociate.');
        }

        return $response;
    }

    public function restore(int $clientId)
    {
        $client = Client::onlyTrashed()->findOrFail($clientId);
        $client->restore();

        return back()->with('status', 'Clientul a fost restaurat din trash.');
    }

    public function bulkRestore(Request $request)
    {
        $data = $request->validate([
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['required', 'integer', 'exists:clienti,id'],
        ]);

        $ids = collect($data['client_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors('Selecteaza cel putin un client.');
        }

        $restoredCount = Client::onlyTrashed()->whereIn('id', $ids)->restore();

        if ($restoredCount <= 0) {
            return back()->withErrors('Nu s-a putut restaura niciun client.');
        }

        $message = $restoredCount === 1
            ? 'Clientul selectat a fost restaurat.'
            : "Cei {$restoredCount} clienti selectati au fost restaurati.";

        return back()->with('status', $message);
    }

    public function forceDelete(int $clientId)
    {
        $client = Client::onlyTrashed()->findOrFail($clientId);
        if ($this->clientHasComenzi($client)) {
            return back()->with('warning', 'Clientul nu poate fi sters definitiv deoarece are comenzi asociate.');
        }

        $client->forceDelete();

        return back()->with('status', 'Clientul a fost sters definitiv.');
    }

    public function bulkForceDelete(Request $request)
    {
        $data = $request->validate([
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['required', 'integer', 'exists:clienti,id'],
        ]);

        $ids = collect($data['client_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors('Selecteaza cel putin un client.');
        }

        $clienti = Client::onlyTrashed()->whereIn('id', $ids)->get();
        $clientiCuComenzi = $clienti
            ->filter(fn (Client $client) => $this->clientHasComenzi($client))
            ->values();
        $clientIdsCuComenzi = $clientiCuComenzi->pluck('id')->all();
        $clientiDeSters = $clienti
            ->reject(fn (Client $client) => in_array($client->id, $clientIdsCuComenzi, true))
            ->pluck('id')
            ->all();

        $deletedCount = empty($clientiDeSters)
            ? 0
            : Client::onlyTrashed()->whereIn('id', $clientiDeSters)->forceDelete();

        if ($deletedCount <= 0 && !empty($clientIdsCuComenzi)) {
            return back()->with('warning', 'Niciun client nu a fost sters definitiv. Clientii selectati au comenzi asociate.');
        }

        if ($deletedCount <= 0) {
            return back()->withErrors('Nu s-a putut sterge definitiv niciun client.');
        }

        $message = $deletedCount === 1
            ? 'Clientul selectat a fost sters definitiv.'
            : "Cei {$deletedCount} clienti selectati au fost stersi definitiv.";

        $response = back()->with('status', $message);
        if (!empty($clientIdsCuComenzi)) {
            $response->with('warning', count($clientIdsCuComenzi) === 1
                ? 'Un client nu a fost sters definitiv deoarece are comenzi asociate.'
                : count($clientIdsCuComenzi) . ' clienti nu au fost stersi definitiv deoarece au comenzi asociate.');
        }

        return $response;
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
                    $query->where('nume', 'like', '%' . $search . '%')
                        ->orWhereHas('emails', fn ($emailQuery) => $emailQuery->where('email', 'like', '%' . $search . '%'));

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
        $data = $this->validateClientPayload($request);
        $emails = $this->extractEmailAddresses($data);
        $data['type'] = $data['type'] ?? 'pf';
        $data['email'] = $emails[0] ?? null;
        unset($data['emails']);

        $client = DB::transaction(function () use ($data, $emails) {
            $client = Client::create($data);
            $client->syncEmailAddresses($emails);

            return $client;
        });

        return response()->json([
            'client' => [
                'id' => $client->id,
                'label' => $this->buildClientLabel($client),
            ],
        ], 201);
    }

    private function listClienti(Request $request, bool $onlyTrashed = false)
    {
        $request->session()->forget('returnUrl');

        $search = $request->search;
        $searchNume = $request->searchNume;
        $searchTelefon = $request->searchTelefon;
        $searchEmail = $request->searchEmail;
        $type = $request->type;
        $sort = (string) $request->get('sort', $onlyTrashed ? 'deleted_at' : 'nume');
        $dir = strtolower((string) $request->get('dir', $onlyTrashed ? 'desc' : 'asc')) === 'desc' ? 'desc' : 'asc';

        $clientiQuery = Client::query();
        if ($onlyTrashed) {
            $clientiQuery->onlyTrashed();
        }

        $clienti = $clientiQuery
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nume', 'like', '%' . $search . '%')
                        ->orWhere('telefon', 'like', '%' . $search . '%')
                        ->orWhere('telefon_secundar', 'like', '%' . $search . '%')
                        ->orWhereHas('emails', fn ($emailQuery) => $emailQuery->where('email', 'like', '%' . $search . '%'));
                });
            })
            ->when($searchNume, fn ($query, $value) => $query->where('nume', 'like', '%' . $value . '%'))
            ->when($searchTelefon, function ($query, $value) {
                $query->where(function ($query) use ($value) {
                    $query->where('telefon', 'like', '%' . $value . '%')
                        ->orWhere('telefon_secundar', 'like', '%' . $value . '%');
                });
            })
            ->when($searchEmail, fn ($query, $value) => $query->whereHas('emails', fn ($emailQuery) => $emailQuery->where('email', 'like', '%' . $value . '%')))
            ->when($type, fn ($query, $value) => $query->where('type', $value))
            ->when($sort === 'nume', fn ($query) => $query->orderBy('nume', $dir))
            ->when($sort === 'telefon', fn ($query) => $query->orderBy('telefon', $dir))
            ->when($sort === 'email', function ($query) use ($dir) {
                $query->orderBy(
                    ClientEmail::query()
                        ->select('email')
                        ->whereColumn('client_emails.client_id', 'clienti.id')
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->limit(1),
                    $dir
                );
            })
            ->when($sort === 'type', fn ($query) => $query->orderBy('type', $dir))
            ->when($sort === 'created_at', fn ($query) => $query->orderBy('created_at', $dir))
            ->when($sort === 'deleted_at', fn ($query) => $query->orderBy('deleted_at', $dir))
            ->when(!in_array($sort, ['nume', 'telefon', 'email', 'type', 'created_at', 'deleted_at'], true), fn ($query) => $query->orderBy('nume'))
            ->orderBy('id')
            ->paginate(25);

        $view = $onlyTrashed ? 'clienti.trash' : 'clienti.index';

        return view($view, compact(
            'clienti',
            'search',
            'searchNume',
            'searchTelefon',
            'searchEmail',
            'type',
            'sort',
            'dir'
        ));
    }

    private function clientHasComenzi(Client $client): bool
    {
        return $client->comenzi()->withTrashed()->exists();
    }

    private function validateClientPayload(Request $request, ?Client $client = null): array
    {
        $type = $request->input('type') ?: ($client?->type ?: 'pf');

        return $request->validate([
            'type' => ['nullable', Rule::in(['pf', 'pj'])],
            'nume' => ['required', 'string', 'max:100'],
            'adresa' => ['nullable', 'string', 'max:255'],
            'telefon' => ['nullable', 'string', 'max:50'],
            'telefon_secundar' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['nullable', 'email', 'max:150'],
            'cnp' => ['nullable', 'string', 'max:13', new CnpRule($type === 'pf')],
            'sex' => ['nullable', 'string', 'max:1', Rule::in(['M', 'F'])],
            'reg_com' => ['nullable', 'string', 'max:50'],
            'cui' => ['nullable', 'string', 'max:20'],
            'iban' => ['nullable', 'string', 'max:50'],
            'banca' => ['nullable', 'string', 'max:100'],
            'reprezentant' => ['nullable', 'string', 'max:150'],
            'reprezentant_functie' => ['nullable', 'string', 'max:150'],
        ]);
    }

    private function extractEmailAddresses(array $data): array
    {
        $emails = collect($data['emails'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->values();

        if ($emails->isEmpty() && !empty($data['email'])) {
            $emails->push($data['email']);
        }

        return $emails
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}

