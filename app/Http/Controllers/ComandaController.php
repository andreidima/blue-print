<?php

namespace App\Http\Controllers;

use App\Enums\MetodaPlata;
use App\Enums\StatusComanda;
use App\Enums\SursaComanda;
use App\Enums\TipComanda;
use App\Models\Client;
use App\Models\Comanda;
use App\Models\ComandaAtasament;
use App\Models\ComandaProdus;
use App\Models\Mockup;
use App\Models\Plata;
use App\Models\Produs;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ComandaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return $this->listComenzi($request);
    }

    public function cereriOferta(Request $request)
    {
        return $this->listComenzi($request, TipComanda::CerereOferta->value, 'Cereri oferta');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $tipuri = TipComanda::options();
        $surse = SursaComanda::options();
        $statusuri = StatusComanda::options();

        return view('comenzi.create', compact('tipuri', 'surse', 'statusuri'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clienti,id'],
            'tip' => ['required', Rule::in(array_keys(TipComanda::options()))],
            'sursa' => ['required', Rule::in(array_keys(SursaComanda::options()))],
            'status' => ['required', Rule::in(array_keys(StatusComanda::options()))],
            'timp_estimat_livrare' => ['required', 'date'],
            'necesita_tipar_exemplu' => ['nullable', 'boolean'],
            'solicitare_client' => ['nullable', 'string'],
            'cantitate_comanda' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['necesita_tipar_exemplu'] = $request->boolean('necesita_tipar_exemplu');
        $data['cantitate'] = $data['cantitate_comanda'] ?? null;
        unset($data['cantitate_comanda']);
        if (in_array($data['status'], StatusComanda::finalStates(), true)) {
            $data['finalizat_la'] = now();
        }

        $comanda = Comanda::create($data);

        $this->storeLinii($request, $comanda);
        $comanda->recalculateTotals();

        return redirect()->route('comenzi.show', $comanda)
            ->with('success', 'Comanda a fost creata cu succes!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Comanda $comanda)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $comanda->load([
            'client',
            'produse.produs',
            'atasamente.uploadedBy',
            'mockupuri.uploadedBy',
            'plati.createdBy',
            'frontdeskUser',
            'supervizorUser',
            'graficianUser',
            'executantUser',
        ]);

        $frontdeskUsers = User::whereHas('roles', fn ($query) => $query->where('slug', 'operator-front-office'))
            ->orderBy('name')
            ->get();
        $graficianUsers = User::whereHas('roles', fn ($query) => $query->where('slug', 'grafician'))
            ->orderBy('name')
            ->get();
        $executantUsers = User::whereHas('roles', fn ($query) => $query->where('slug', 'operator-tipografie'))
            ->orderBy('name')
            ->get();
        $supervizorUsers = User::whereHas('roles', fn ($query) => $query->where('slug', 'supervizor'))
            ->orderBy('name')
            ->get();

        $frontdeskUsers = $frontdeskUsers
            ->push($comanda->frontdeskUser)
            ->filter()
            ->unique('id')
            ->values();
        $graficianUsers = $graficianUsers
            ->push($comanda->graficianUser)
            ->filter()
            ->unique('id')
            ->values();
        $executantUsers = $executantUsers
            ->push($comanda->executantUser)
            ->filter()
            ->unique('id')
            ->values();
        $supervizorUsers = $supervizorUsers
            ->push($comanda->supervizorUser)
            ->filter()
            ->reject(fn (User $user) => $user->hasAnyRole(['superadmin']))
            ->unique('id')
            ->values();

        $produse = Produs::where('activ', true)->orderBy('denumire')->get();

        $tipuri = TipComanda::options();
        $surse = SursaComanda::options();
        $statusuri = StatusComanda::options();
        $metodePlata = MetodaPlata::options();

        return view('comenzi.show', compact(
            'comanda',
            'frontdeskUsers',
            'supervizorUsers',
            'graficianUsers',
            'executantUsers',
            'produse',
            'tipuri',
            'surse',
            'statusuri',
            'metodePlata',
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Comanda $comanda)
    {
        $user = $request->user();

        $rules = [
            'client_id' => ['sometimes', 'required', 'exists:clienti,id'],
            'status' => ['required', Rule::in(array_keys(StatusComanda::options()))],
            'timp_estimat_livrare' => ['required', 'date'],
            'necesita_tipar_exemplu' => ['nullable', 'boolean'],
        ];

        if ($comanda->canEditAssignments($user)) {
            $rules['frontdesk_user_id'] = ['nullable', 'exists:users,id'];
            $rules['supervizor_user_id'] = ['nullable', 'exists:users,id'];
            $rules['grafician_user_id'] = ['nullable', 'exists:users,id'];
            $rules['executant_user_id'] = ['nullable', 'exists:users,id'];
        }

        if ($comanda->canEditNotaFrontdesk($user)) {
            $rules['nota_frontdesk'] = ['nullable', 'string'];
            $rules['solicitare_client'] = ['nullable', 'string'];
            $rules['cantitate_comanda'] = ['nullable', 'integer', 'min:1'];
        }
        if ($comanda->canEditNotaGrafician($user)) {
            $rules['nota_grafician'] = ['nullable', 'string'];
        }
        if ($comanda->canEditNotaExecutant($user)) {
            $rules['nota_executant'] = ['nullable', 'string'];
        }

        $data = $request->validate($rules);

        $data['necesita_tipar_exemplu'] = $request->boolean('necesita_tipar_exemplu');
        if (array_key_exists('cantitate_comanda', $data)) {
            $data['cantitate'] = $data['cantitate_comanda'];
            unset($data['cantitate_comanda']);
        }

        if (in_array($data['status'], StatusComanda::finalStates(), true)) {
            $data['finalizat_la'] = $comanda->finalizat_la ?? now();
        } else {
            $data['finalizat_la'] = null;
        }

        $comanda->update($data);

        return back()->with('status', 'Comanda a fost actualizata cu succes!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Comanda $comanda)
    {
        $comanda->delete();

        return redirect()->route('comenzi.index')->with('status', 'Comanda a fost stearsa cu succes!');
    }

    public function storeProdus(Request $request, Comanda $comanda)
    {
        $produsTip = $request->input('produs_tip', 'existing');
        if (!in_array($produsTip, ['existing', 'custom'], true)) {
            $produsTip = 'existing';
        }

        if ($produsTip === 'custom') {
            $data = $request->validate([
                'produs_tip' => ['required', Rule::in(['existing', 'custom'])],
                'custom_denumire' => ['required', 'string', 'max:255'],
                'custom_pret_unitar' => ['required', 'numeric', 'min:0'],
                'cantitate' => ['required', 'integer', 'min:1'],
            ]);

            $pretUnitar = round((float) $data['custom_pret_unitar'], 2);
            $totalLinie = round($pretUnitar * $data['cantitate'], 2);

            $comanda->produse()->create([
                'produs_id' => null,
                'custom_denumire' => $data['custom_denumire'],
                'cantitate' => $data['cantitate'],
                'pret_unitar' => $pretUnitar,
                'total_linie' => $totalLinie,
            ]);
        } else {
            $data = $request->validate([
                'produs_tip' => ['nullable', Rule::in(['existing', 'custom'])],
                'produs_id' => ['required', 'exists:produse,id'],
                'cantitate' => ['required', 'integer', 'min:1'],
            ]);

            $produs = Produs::findOrFail($data['produs_id']);
            $totalLinie = round($produs->pret * $data['cantitate'], 2);

            $comanda->produse()->create([
                'produs_id' => $produs->id,
                'cantitate' => $data['cantitate'],
                'pret_unitar' => $produs->pret,
                'total_linie' => $totalLinie,
            ]);
        }

        $comanda->recalculateTotals();

        $message = $produsTip === 'custom'
            ? 'Produsul custom a fost adaugat pe comanda.'
            : 'Produsul a fost adaugat pe comanda.';

        return back()->with('success', $message);
    }

    public function storeAtasament(Request $request, Comanda $comanda)
    {
        $data = $request->validate([
            'atasament' => ['required', 'array'],
            'atasament.*' => ['file', 'max:10240'],
        ]);

        $files = $request->file('atasament', []);
        if (!is_array($files)) {
            $files = [$files];
        }

        $count = 0;
        foreach ($files as $file) {
            $path = $file->store('comenzi/' . $comanda->id . '/atasamente', 'public');

            ComandaAtasament::create([
                'comanda_id' => $comanda->id,
                'uploaded_by' => auth()->id(),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);

            $count++;
        }

        $message = $count === 1
            ? 'Atasamentul a fost incarcat.'
            : "Au fost incarcate {$count} atasamente.";

        return back()->with('success', $message);
    }

    public function storeMockup(Request $request, Comanda $comanda)
    {
        $data = $request->validate([
            'mockup' => ['required', 'array'],
            'mockup.*' => ['file', 'max:10240'],
            'comentariu' => ['nullable', 'string'],
        ]);

        $files = $request->file('mockup', []);
        if (!is_array($files)) {
            $files = [$files];
        }

        $count = 0;
        foreach ($files as $file) {
            $path = $file->store('comenzi/' . $comanda->id . '/mockupuri', 'public');

            Mockup::create([
                'comanda_id' => $comanda->id,
                'uploaded_by' => auth()->id(),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'comentariu' => $data['comentariu'] ?? null,
            ]);

            $count++;
        }

        $message = $count === 1
            ? 'Mockup-ul a fost incarcat.'
            : "Au fost incarcate {$count} mockup-uri.";

        return back()->with('success', $message);
    }

    public function viewAtasament(Comanda $comanda, ComandaAtasament $atasament)
    {
        abort_unless($atasament->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($atasament->path), 404);

        return $disk->response($atasament->path, $atasament->original_name, [], 'inline');
    }

    public function downloadAtasament(Comanda $comanda, ComandaAtasament $atasament)
    {
        abort_unless($atasament->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($atasament->path), 404);

        return $disk->download($atasament->path, $atasament->original_name);
    }

    public function destroyAtasament(Comanda $comanda, ComandaAtasament $atasament)
    {
        abort_unless($atasament->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        if ($disk->exists($atasament->path)) {
            $disk->delete($atasament->path);
        }

        $atasament->delete();

        return back()->with('success', 'Atasamentul a fost sters.');
    }

    public function viewMockup(Comanda $comanda, Mockup $mockup)
    {
        abort_unless($mockup->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($mockup->path), 404);

        return $disk->response($mockup->path, $mockup->original_name, [], 'inline');
    }

    public function downloadMockup(Comanda $comanda, Mockup $mockup)
    {
        abort_unless($mockup->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($mockup->path), 404);

        return $disk->download($mockup->path, $mockup->original_name);
    }

    public function destroyMockup(Comanda $comanda, Mockup $mockup)
    {
        abort_unless($mockup->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        if ($disk->exists($mockup->path)) {
            $disk->delete($mockup->path);
        }

        $mockup->delete();

        return back()->with('success', 'Mockup-ul a fost sters.');
    }

    public function storePlata(Request $request, Comanda $comanda)
    {
        $data = $request->validate([
            'suma' => ['required', 'numeric', 'min:0.01'],
            'metoda' => ['required', Rule::in(array_keys(MetodaPlata::options()))],
            'numar_factura' => ['nullable', 'string', 'max:50'],
            'platit_la' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        Plata::create([
            'comanda_id' => $comanda->id,
            'suma' => $data['suma'],
            'metoda' => $data['metoda'],
            'numar_factura' => $data['numar_factura'] ?? null,
            'platit_la' => Carbon::parse($data['platit_la']),
            'note' => $data['note'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $comanda->recalculateTotals();

        return back()->with('success', 'Plata a fost inregistrata.');
    }

    public function trimiteSms(Comanda $comanda)
    {
        return back()->with('warning', 'Trimiterea SMS nu este implementata inca.');
    }

    public function trimiteEmail(Comanda $comanda)
    {
        return back()->with('warning', 'Trimiterea email nu este implementata inca.');
    }

    private function listComenzi(Request $request, ?string $fixedTip = null, ?string $pageTitle = null)
    {
        $tip = $fixedTip ?? $request->tip;
        $status = $request->status;
        $sursa = $request->sursa;
        $client = $request->client;
        $dataDe = $request->timp_de;
        $dataPana = $request->timp_pana;
        $overdue = $request->boolean('overdue');
        $asignateMie = $request->boolean('asignate_mie');

        $query = Comanda::with('client')
            ->when($tip, fn ($query) => $query->where('tip', $tip))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($sursa, fn ($query) => $query->where('sursa', $sursa))
            ->when($client, function ($query, $client) {
                $query->whereHas('client', function ($query) use ($client) {
                    $query->where('nume', 'like', '%' . $client . '%')
                        ->orWhere('telefon', 'like', '%' . $client . '%')
                        ->orWhere('email', 'like', '%' . $client . '%');
                });
            })
            ->when($dataDe, function ($query, $dataDe) {
                $query->where('timp_estimat_livrare', '>=', Carbon::parse($dataDe)->startOfDay());
            })
            ->when($dataPana, function ($query, $dataPana) {
                $query->where('timp_estimat_livrare', '<=', Carbon::parse($dataPana)->endOfDay());
            })
            ->when($overdue, fn ($query) => $query->overdue())
            ->when($asignateMie, fn ($query) => $query->assignedTo(auth()->id()))
            ->orderBy('timp_estimat_livrare');

        $comenzi = $query->simplePaginate(25);

        $tipuri = TipComanda::options();
        $surse = SursaComanda::options();
        $statusuri = StatusComanda::options();

        return view('comenzi.index', [
            'comenzi' => $comenzi,
            'tip' => $tip,
            'status' => $status,
            'sursa' => $sursa,
            'client' => $client,
            'dataDe' => $dataDe,
            'dataPana' => $dataPana,
            'overdue' => $overdue,
            'asignateMie' => $asignateMie,
            'tipuri' => $tipuri,
            'surse' => $surse,
            'statusuri' => $statusuri,
            'pageTitle' => $pageTitle,
            'fixedTip' => $fixedTip,
        ]);
    }

    private function storeLinii(Request $request, Comanda $comanda): void
    {
        $produsIds = $request->input('produs_id', []);
        $cantitati = $request->input('cantitate', []);

        $validProdusIds = array_values(array_filter($produsIds));
        if (empty($validProdusIds)) {
            return;
        }

        $produse = Produs::whereIn('id', $validProdusIds)->get()->keyBy('id');

        foreach ($produsIds as $index => $produsId) {
            if (empty($produsId)) {
                continue;
            }

            $cantitate = isset($cantitati[$index]) ? (int) $cantitati[$index] : 0;
            if ($cantitate <= 0 || !isset($produse[$produsId])) {
                continue;
            }

            $produs = $produse[$produsId];
            $totalLinie = round($produs->pret * $cantitate, 2);

            ComandaProdus::create([
                'comanda_id' => $comanda->id,
                'produs_id' => $produs->id,
                'cantitate' => $cantitate,
                'pret_unitar' => $produs->pret,
                'total_linie' => $totalLinie,
            ]);
        }
    }
}
