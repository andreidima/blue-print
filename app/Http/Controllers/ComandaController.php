<?php

namespace App\Http\Controllers;

use App\Enums\MetodaPlata;
use App\Enums\StatusComanda;
use App\Enums\StatusPlata;
use App\Enums\SursaComanda;
use App\Enums\TipComanda;
use App\Mail\ComandaFacturaMail;
use App\Models\Client;
use App\Models\Comanda;
use App\Models\ComandaAtasament;
use App\Models\ComandaEtapaUser;
use App\Models\ComandaFactura;
use App\Models\ComandaFacturaEmail;
use App\Models\ComandaGdprConsent;
use App\Models\ComandaProdus;
use App\Models\ComandaOfertaEmail;
use App\Models\ComandaSolicitare;
use App\Models\ComandaNota;
use App\Models\ComandaEmailLog;
use App\Models\EmailTemplate;
use App\Models\Etapa;
use App\Models\Mockup;
use App\Models\Plata;
use App\Models\Produs;
use App\Models\User;
use App\Support\EmailContent;
use App\Support\EmailPlaceholders;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ComandaController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkUserPermission:comenzi.write')->only([
            'store',
            'update',
            'destroy',
            'storeSolicitari',
            'destroySolicitare',
            'updateSolicitare',
            'storeNote',
            'updateNote',
            'destroyNote',
            'storeGdprConsent',
        ]);
        $this->middleware('checkUserPermission:comenzi.produse.write')->only(['storeProdus', 'destroyProdus']);
        $this->middleware('checkUserPermission:comenzi.atasamente.write')->only(['storeAtasament', 'destroyAtasament']);
        $this->middleware('checkUserPermission:comenzi.mockupuri.write')->only(['storeMockup', 'destroyMockup']);
        $this->middleware('checkUserPermission:comenzi.plati.write')->only(['storePlata', 'destroyPlata']);
        $this->middleware('checkUserPermission:comenzi.etape.write')->only(['approveAssignments']);
        $this->middleware('checkUserPermission:facturi.write')->only(['storeFactura', 'destroyFactura']);
        $this->middleware('checkUserPermission:facturi.email.send')->only(['trimiteFacturaEmail', 'trimiteEmail']);
        $this->middleware('checkUserPermission:comenzi.email.send')->only(['trimiteOfertaEmail', 'trimiteGdprEmail']);
    }
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
            'data_solicitarii' => ['required', 'date'],
            'timp_estimat_livrare' => ['required', 'date'],
            'necesita_tipar_exemplu' => ['nullable', 'boolean'],
            'necesita_mockup' => ['nullable', 'boolean'],
            'solicitari' => ['nullable', 'array'],
            'solicitari.*.solicitare_client' => ['nullable', 'string'],
            'solicitari.*.cantitate' => ['nullable', 'integer', 'min:1'],
            'awb' => ['nullable', 'string', 'max:50'],
        ]);

        $data['necesita_tipar_exemplu'] = $request->boolean('necesita_tipar_exemplu');
        $data['necesita_mockup'] = $request->boolean('necesita_mockup');
        if (in_array($data['status'], StatusComanda::finalStates(), true)) {
            $data['finalizat_la'] = now();
        }

        $comanda = Comanda::create($data);

        $this->storeSolicitariFromRequest($request, $comanda);

        $creatorId = $request->user()?->id;
        if ($creatorId) {
            $preluareEtapaId = Etapa::where('slug', 'preluare_comanda')->value('id');
            if ($preluareEtapaId) {
                $comanda->etapaAssignments()->firstOrCreate(
                    [
                        'etapa_id' => $preluareEtapaId,
                        'user_id' => $creatorId,
                    ],
                    [
                        'status' => ComandaEtapaUser::STATUS_APPROVED,
                    ],
                );
            }
        }

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
            'facturi' => fn ($query) => $query->latest(),
            'facturi.uploadedBy',
            'facturaEmails' => fn ($query) => $query->latest(),
            'facturaEmails.sentBy',
            'ofertaEmails' => fn ($query) => $query->latest(),
            'ofertaEmails.sentBy',
            'mockupuri' => fn ($query) => $query->latest()->with('uploadedBy'),
            'plati.createdBy',
            'supervizorUser',
            'etapaAssignments',
            'solicitari.createdBy',
            'note.createdBy',
            'gdprConsents' => fn ($query) => $query->latest('signed_at'),
        ]);

        $activeUsers = User::where('activ', true)
            ->whereDoesntHave('roles', fn ($query) => $query->where('slug', 'superadmin'))
            ->orderBy('name')
            ->get();
        $activeUsers = $activeUsers
            ->push($comanda->supervizorUser)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $etape = Etapa::orderBy('id')->get();
        $assignedUserIdsByEtapa = $comanda->etapaAssignments
            ->groupBy('etapa_id')
            ->map(fn ($items) => $items->pluck('user_id')->map(fn ($id) => (string) $id)->values()->all())
            ->all();
        $assignmentStatusesByEtapaUser = $comanda->etapaAssignments
            ->groupBy('etapa_id')
            ->map(fn ($items) => $items->mapWithKeys(fn ($item) => [(string) $item->user_id => $item->status])->all())
            ->all();

        $produse = Produs::where('activ', true)->orderBy('denumire')->get();

        $tipuri = TipComanda::options();
        $surse = SursaComanda::options();
        $statusuri = StatusComanda::options();
        $metodePlata = MetodaPlata::options();

        $emailTemplates = EmailTemplate::query()
            ->active()
            ->orderBy('name')
            ->get();
        if ($emailTemplates->isEmpty()) {
            $emailTemplates = EmailTemplate::query()->orderBy('name')->get();
        }
        $emailPlaceholders = EmailPlaceholders::forComanda($comanda);

        return view('comenzi.show', compact(
            'comanda',
            'activeUsers',
            'etape',
            'assignedUserIdsByEtapa',
            'assignmentStatusesByEtapaUser',
            'produse',
            'tipuri',
            'surse',
            'statusuri',
            'metodePlata',
            'emailTemplates',
            'emailPlaceholders',
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
            'tip' => ['required', Rule::in(array_keys(TipComanda::options()))],
            'sursa' => ['required', Rule::in(array_keys(SursaComanda::options()))],
            'status' => ['required', Rule::in(array_keys(StatusComanda::options()))],
            'data_solicitarii' => ['required', 'date'],
            'timp_estimat_livrare' => ['required', 'date'],
            'necesita_tipar_exemplu' => ['nullable', 'boolean'],
            'necesita_mockup' => ['nullable', 'boolean'],
            'awb' => ['nullable', 'string', 'max:50'],
        ];

        if ($comanda->canEditAssignments($user)) {
            $rules['etape'] = ['nullable', 'array'];
            $rules['etape.*'] = ['array'];
            $rules['etape.*.*'] = ['nullable', 'integer', 'exists:users,id'];
        }

        $data = $request->validate($rules);

        $data['necesita_tipar_exemplu'] = $request->boolean('necesita_tipar_exemplu');
        $data['necesita_mockup'] = $request->boolean('necesita_mockup');

        if (in_array($data['status'], StatusComanda::finalStates(), true)) {
            $data['finalizat_la'] = $comanda->finalizat_la ?? now();
        } else {
            $data['finalizat_la'] = null;
        }

        $comanda->update($data);

        if ($comanda->canEditAssignments($user)) {
            $etapeInput = $request->input('etape', []);
            $etapaIds = Etapa::pluck('id')->all();
            $assignableUserIds = User::where('activ', true)
                ->whereDoesntHave('roles', fn ($query) => $query->where('slug', 'superadmin'))
                ->pluck('id')
                ->all();
            if ($comanda->supervizor_user_id) {
                $assignableUserIds[] = $comanda->supervizor_user_id;
            }
            $assignableUserIds = array_map('intval', $assignableUserIds);
            $assignableUserIds = array_values(array_unique($assignableUserIds));

            foreach ($etapaIds as $etapaId) {
                $requestedUserIds = collect($etapeInput[$etapaId] ?? [])
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->map(fn ($value) => (int) $value)
                    ->unique()
                    ->filter(fn ($value) => in_array($value, $assignableUserIds, true))
                    ->values()
                    ->all();

                $existingUserIds = $comanda->etapaAssignments()
                    ->where('etapa_id', $etapaId)
                    ->pluck('user_id')
                    ->map(fn ($value) => (int) $value)
                    ->all();

                $userIdsToDelete = array_diff($existingUserIds, $requestedUserIds);
                if (!empty($userIdsToDelete)) {
                    $comanda->etapaAssignments()
                        ->where('etapa_id', $etapaId)
                        ->whereIn('user_id', $userIdsToDelete)
                        ->delete();
                }

                $userIdsToAdd = array_diff($requestedUserIds, $existingUserIds);
                foreach ($userIdsToAdd as $userId) {
                    $comanda->etapaAssignments()->create([
                        'etapa_id' => $etapaId,
                        'user_id' => $userId,
                        'status' => ComandaEtapaUser::STATUS_PENDING,
                    ]);
                }
            }
        }

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

    public function storeSolicitari(Request $request, Comanda $comanda)
    {
        $user = $request->user();
        abort_unless($comanda->canEditNotaFrontdesk($user), 403);

        $request->validate([
            'solicitari' => ['nullable', 'array'],
            'solicitari.*.solicitare_client' => ['nullable', 'string'],
            'solicitari.*.cantitate' => ['nullable', 'integer', 'min:1'],
        ]);

        $added = $this->storeSolicitariFromRequest($request, $comanda);

        if ($added === 0) {
            return back()->with('warning', 'Nu exista informatii de salvat.');
        }

        $message = $added === 1
            ? 'Informatiile comenzii au fost adaugate.'
            : "Au fost adaugate {$added} informatii pe comanda.";

        return back()->with('success', $message);
    }

    public function destroySolicitare(Request $request, Comanda $comanda, ComandaSolicitare $solicitare)
    {
        $user = $request->user();
        abort_unless($comanda->canEditNotaFrontdesk($user), 403);
        abort_unless($solicitare->comanda_id === $comanda->id, 404);

        $solicitare->delete();

        return back()->with('success', 'Informatiile comenzii au fost sterse.');
    }

    public function updateSolicitare(Request $request, Comanda $comanda, ComandaSolicitare $solicitare)
    {
        $user = $request->user();
        abort_unless($comanda->canEditNotaFrontdesk($user), 403);
        abort_unless($solicitare->comanda_id === $comanda->id, 404);

        $data = $request->validate([
            'solicitare_client' => ['nullable', 'string'],
            'cantitate' => ['nullable', 'integer', 'min:1'],
        ]);

        $solicitare->update($data);

        return back()->with('success', 'Solicitarea a fost actualizata.');
    }

    public function storeNote(Request $request, Comanda $comanda, string $role)
    {
        $user = $request->user();
        $role = $this->normalizeNoteRole($role);
        if (!$role) {
            abort(404);
        }

        $this->ensureCanEditNoteRole($comanda, $user, $role);

        $request->validate([
            'note_entries' => ['nullable', 'array'],
            'note_entries.*.nota' => ['nullable', 'string'],
        ]);

        $added = $this->storeNotesFromRequest($request, $comanda, $role);
        if ($added === 0) {
            return back()->with('warning', 'Nu exista note de salvat.');
        }

        $message = $added === 1
            ? 'Nota a fost adaugata.'
            : "Au fost adaugate {$added} note.";

        return back()->with('success', $message);
    }

    public function updateNote(Request $request, Comanda $comanda, ComandaNota $nota)
    {
        $user = $request->user();
        abort_unless($nota->comanda_id === $comanda->id, 404);

        $role = $this->normalizeNoteRole($nota->role);
        if (!$role) {
            abort(404);
        }

        $this->ensureCanEditNoteRole($comanda, $user, $role);

        $data = $request->validate([
            'nota' => ['nullable', 'string'],
        ]);

        $text = trim((string) ($data['nota'] ?? ''));
        if ($text === '') {
            return back()->with('warning', 'Nota nu poate fi goala.');
        }

        $nota->update(['nota' => $text]);

        return back()->with('success', 'Nota a fost actualizata.');
    }

    public function destroyNote(Request $request, Comanda $comanda, ComandaNota $nota)
    {
        $user = $request->user();
        abort_unless($nota->comanda_id === $comanda->id, 404);

        $role = $this->normalizeNoteRole($nota->role);
        if (!$role) {
            abort(404);
        }

        $this->ensureCanEditNoteRole($comanda, $user, $role);

        $nota->delete();

        return back()->with('success', 'Nota a fost stearsa.');
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

        if ($request->wantsJson()) {
            return response()->json($this->buildComandaAjaxPayload($comanda, $message));
        }

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

    public function storeFactura(Request $request, Comanda $comanda)
    {
        $this->ensureCanManageFacturi($request->user());

        $request->validate([
            'factura' => ['required', 'array'],
            'factura.*' => ['file', 'max:10240'],
        ]);

        $files = $request->file('factura', []);
        if (!is_array($files)) {
            $files = [$files];
        }

        $count = 0;
        foreach ($files as $file) {
            $path = $file->store('comenzi/' . $comanda->id . '/facturi', 'public');

            ComandaFactura::create([
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
            ? 'Factura a fost incarcata.'
            : "Au fost incarcate {$count} facturi.";

        return back()->with('success', $message);
    }

    public function storeMockup(Request $request, Comanda $comanda)
    {
        $data = $request->validate([
            'tip' => ['required', Rule::in(array_keys(Mockup::typeOptions()))],
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
                'tip' => $data['tip'],
                'uploaded_by' => auth()->id(),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'comentariu' => $data['comentariu'] ?? null,
            ]);

            $count++;
        }

        $tipLabel = Mockup::typeOptions()[$data['tip']] ?? 'Info';
        $message = $count === 1
            ? "{$tipLabel} a fost incarcata."
            : "Au fost incarcate {$count} fisiere pentru {$tipLabel}.";

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

    public function viewFactura(Request $request, Comanda $comanda, ComandaFactura $factura)
    {
        $this->ensureCanViewFacturi($request->user());
        abort_unless($factura->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($factura->path), 404);

        return $disk->response($factura->path, $factura->original_name, [], 'inline');
    }

    public function downloadFactura(Request $request, Comanda $comanda, ComandaFactura $factura)
    {
        $this->ensureCanViewFacturi($request->user());
        abort_unless($factura->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($factura->path), 404);

        return $disk->download($factura->path, $factura->original_name);
    }

    public function downloadFacturaPublic(Comanda $comanda, ComandaFactura $factura)
    {
        abort_unless($factura->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($factura->path), 404);

        return $disk->download($factura->path, $factura->original_name);
    }

    public function destroyFactura(Request $request, Comanda $comanda, ComandaFactura $factura)
    {
        $this->ensureCanManageFacturi($request->user());
        abort_unless($factura->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        if ($disk->exists($factura->path)) {
            $disk->delete($factura->path);
        }

        $factura->delete();

        return back()->with('success', 'Factura a fost stearsa.');
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

        $message = 'Plata a fost inregistrata.';

        if ($request->wantsJson()) {
            return response()->json($this->buildComandaAjaxPayload($comanda, $message));
        }

        return back()->with('success', $message);
    }

    public function destroyProdus(Request $request, Comanda $comanda, ComandaProdus $linie)
    {
        abort_unless($linie->comanda_id === $comanda->id, 404);

        $linie->delete();
        $comanda->recalculateTotals();

        $message = 'Produsul a fost eliminat.';

        if ($request->wantsJson()) {
            return response()->json($this->buildComandaAjaxPayload($comanda, $message));
        }

        return back()->with('success', $message);
    }

    public function destroyPlata(Request $request, Comanda $comanda, Plata $plata)
    {
        abort_unless($plata->comanda_id === $comanda->id, 404);

        $plata->delete();
        $comanda->recalculateTotals();

        $message = 'Plata a fost eliminata.';

        if ($request->wantsJson()) {
            return response()->json($this->buildComandaAjaxPayload($comanda, $message));
        }

        return back()->with('success', $message);
    }

    public function approveAssignments(Request $request, Comanda $comanda)
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return back()->with('warning', 'Trebuie sa fii autentificat pentru a aproba cererea.');
        }

        $updated = ComandaEtapaUser::where('comanda_id', $comanda->id)
            ->where('user_id', $userId)
            ->where('status', ComandaEtapaUser::STATUS_PENDING)
            ->update(['status' => ComandaEtapaUser::STATUS_APPROVED]);

        if ($updated === 0) {
            return back()->with('warning', 'Nu exista cereri in asteptare pentru aceasta comanda.');
        }

        return back()->with('success', 'Cererea a fost aprobata.');
    }

    public function trimiteSms(Comanda $comanda)
    {
        return redirect()->route('comenzi.sms.show', $comanda);
    }

    public function trimiteFacturaEmail(Request $request, Comanda $comanda)
    {
        $this->ensureCanSendFacturiEmail($request->user());

        $data = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $recipient = optional($comanda->client)->email;
        if (!$recipient) {
            return back()->with('warning', 'Clientul nu are un email setat.');
        }

        $comanda->load(['client', 'produse.produs']);
        $facturi = $comanda->facturi()->latest()->get();
        if ($facturi->isEmpty()) {
            return back()->with('warning', 'Nu exista facturi de trimis.');
        }

        $placeholders = EmailPlaceholders::forComanda($comanda);
        $subject = EmailContent::replacePlaceholders($data['subject'], $placeholders);
        $bodyHtml = EmailContent::formatBody($data['body'], $placeholders);
        $downloadLinks = $facturi->map(function (ComandaFactura $factura) use ($comanda) {
            return [
                'label' => $factura->original_name ?: 'Factura',
                'url' => URL::temporarySignedRoute(
                    'comenzi.facturi.public-download',
                    now()->addDays(30),
                    ['comanda' => $comanda->id, 'factura' => $factura->id]
                ),
            ];
        })->values()->all();

        try {
            Mail::to($recipient)->send(
                new ComandaFacturaMail($comanda, $subject, $bodyHtml, $downloadLinks)
            );
        } catch (Throwable $e) {
            Log::error('Trimitere factura esuata.', [
                'comanda_id' => $comanda->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return back()->with('warning', 'Trimiterea emailului a esuat.');
        }

        $facturiSnapshot = $facturi->map(fn (ComandaFactura $factura) => [
            'id' => $factura->id,
            'original_name' => $factura->original_name,
            'path' => $factura->path,
            'mime' => $factura->mime,
            'size' => $factura->size,
        ])->values()->all();

        ComandaFacturaEmail::create([
            'comanda_id' => $comanda->id,
            'sent_by' => $request->user()?->id,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $bodyHtml,
            'facturi' => $facturiSnapshot,
        ]);

        return back()->with('success', 'Emailul cu factura a fost trimis.');
    }

    public function trimiteEmail(Request $request, Comanda $comanda)
    {
        return $this->trimiteFacturaEmail($request, $comanda);
    }

    public function downloadOfertaPdf(Comanda $comanda)
    {
        $comanda->load(['client', 'produse.produs', 'solicitari.createdBy']);

        $pdf = Pdf::loadView('pdf.comenzi.oferta', [
            'comanda' => $comanda,
        ]);

        return $pdf->download("oferta-comanda-{$comanda->id}.pdf");
    }

    public function downloadOfertaPdfSigned(Comanda $comanda)
    {
        return $this->downloadOfertaPdf($comanda);
    }

    public function downloadFisaInternaPdf(Comanda $comanda)
    {
        $comanda->load([
            'client',
            'produse.produs',
            'etapaAssignments.etapa',
            'etapaAssignments.user',
            'solicitari.createdBy',
        ]);

        $pdf = Pdf::loadView('pdf.comenzi.fisa-interna', [
            'comanda' => $comanda,
        ]);

        return $pdf->download("fisa-interna-comanda-{$comanda->id}.pdf");
    }

    public function downloadProcesVerbalPdf(Comanda $comanda)
    {
        $comanda->load(['client', 'produse.produs']);

        $pdf = Pdf::loadView('pdf.comenzi.proces-verbal', [
            'comanda' => $comanda,
        ]);

        return $pdf->download("proces-verbal-predare-comanda-{$comanda->id}.pdf");
    }

    public function trimiteOfertaEmail(Request $request, Comanda $comanda)
    {
        $data = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $recipient = optional($comanda->client)->email;
        if (!$recipient) {
            return back()->with('warning', 'Clientul nu are un email setat.');
        }

        $comanda->load(['client', 'produse.produs']);

        $placeholders = EmailPlaceholders::forComanda($comanda);
        $subject = EmailContent::replacePlaceholders($data['subject'], $placeholders);
        $bodyHtml = EmailContent::formatBody($data['body'], $placeholders);
        $downloadUrl = URL::temporarySignedRoute(
            'comenzi.pdf.oferta.signed',
            now()->addDays(30),
            ['comanda' => $comanda->id]
        );

        try {
            Mail::send('emails.comenzi.oferta', [
                'comanda' => $comanda,
                'bodyHtml' => $bodyHtml,
                'downloadUrl' => $downloadUrl,
            ], function ($message) use ($recipient, $subject) {
                $message->to($recipient)
                    ->subject($subject);
            });
        } catch (Throwable $e) {
            Log::error('Trimitere oferta esuata.', [
                'comanda_id' => $comanda->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return back()->with('warning', 'Trimiterea emailului a esuat.');
        }

        ComandaOfertaEmail::create([
            'comanda_id' => $comanda->id,
            'sent_by' => $request->user()?->id,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $bodyHtml,
            'pdf_name' => "oferta-comanda-{$comanda->id}.pdf",
            'privacy_notice_sent_at' => now(),
        ]);

        return back()->with('success', 'Oferta PDF a fost trimisa pe email.');
    }

    public function storeGdprConsent(Request $request, Comanda $comanda)
    {
        $data = $request->validate([
            'method' => ['nullable', Rule::in(['signature', 'checkbox'])],
            'consent_processing' => ['accepted'],
            'consent_marketing' => ['nullable', 'boolean'],
            'signature_data' => ['nullable', 'string'],
        ]);

        $method = $data['method'] ?? 'signature';
        $signaturePath = null;

        if ($method === 'signature') {
            $signatureData = $data['signature_data'] ?? null;
            if (!$signatureData || !Str::startsWith($signatureData, 'data:image/png;base64,')) {
                return back()->with('warning', 'Semnatura este invalida sau lipseste.');
            }

            $signatureBinary = base64_decode(substr($signatureData, strlen('data:image/png;base64,')));
            if ($signatureBinary === false) {
                return back()->with('warning', 'Semnatura nu a putut fi procesata.');
            }

            $path = "comenzi/{$comanda->id}/gdpr/semnatura-" . now()->format('YmdHis') . '-' . Str::random(6) . '.png';
            $stored = Storage::disk('public')->put($path, $signatureBinary);
            if (!$stored) {
                return back()->with('warning', 'Semnatura nu a putut fi salvata.');
            }
            $signaturePath = $path;
        }

        $client = $comanda->client;
        $clientSnapshot = $client ? [
            'type' => $client->type,
            'nume' => $client->nume_complet,
            'adresa' => $client->adresa,
            'telefon' => $client->telefon,
            'telefon_secundar' => $client->telefon_secundar,
            'email' => $client->email,
            'cnp' => $client->cnp,
            'sex' => $client->sex,
            'reg_com' => $client->reg_com,
            'cui' => $client->cui,
            'iban' => $client->iban,
            'banca' => $client->banca,
            'reprezentant' => $client->reprezentant,
            'reprezentant_functie' => $client->reprezentant_functie,
        ] : null;

        ComandaGdprConsent::create([
            'comanda_id' => $comanda->id,
            'method' => $method,
            'consent_processing' => true,
            'consent_marketing' => $request->boolean('consent_marketing'),
            'signature_path' => $signaturePath,
            'signed_at' => now(),
            'client_snapshot' => $clientSnapshot,
            'created_by' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Acordul GDPR a fost salvat.');
    }

    public function downloadGdprPdf(Comanda $comanda)
    {
        $comanda->load(['client']);
        $consent = $comanda->gdprConsents()->latest('signed_at')->first();
        if (!$consent) {
            return back()->with('warning', 'Nu exista un acord GDPR inregistrat.');
        }

        $pdf = Pdf::loadView('pdf.comenzi.gdpr', [
            'comanda' => $comanda,
            'consent' => $consent,
        ]);

        return $pdf->download("gdpr-comanda-{$comanda->id}.pdf");
    }

    public function downloadGdprPdfSigned(Comanda $comanda)
    {
        $comanda->load(['client']);
        $consent = $comanda->gdprConsents()->latest('signed_at')->first();
        if (!$consent) {
            abort(404);
        }

        $pdf = Pdf::loadView('pdf.comenzi.gdpr', [
            'comanda' => $comanda,
            'consent' => $consent,
        ]);

        return $pdf->download("gdpr-comanda-{$comanda->id}.pdf");
    }

    public function trimiteGdprEmail(Request $request, Comanda $comanda)
    {
        $data = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $recipient = optional($comanda->client)->email;
        if (!$recipient) {
            return back()->with('warning', 'Clientul nu are un email setat.');
        }

        $comanda->load(['client']);
        $consent = $comanda->gdprConsents()->latest('signed_at')->first();
        if (!$consent) {
            return back()->with('warning', 'Nu exista un acord GDPR inregistrat.');
        }

        $placeholders = EmailPlaceholders::forComanda($comanda);
        $subject = EmailContent::replacePlaceholders($data['subject'], $placeholders);
        $bodyHtml = EmailContent::formatBody($data['body'], $placeholders);
        $downloadUrl = URL::temporarySignedRoute(
            'comenzi.pdf.gdpr.signed',
            now()->addDays(30),
            ['comanda' => $comanda->id]
        );

        try {
            Mail::send('emails.comenzi.gdpr', [
                'comanda' => $comanda,
                'bodyHtml' => $bodyHtml,
                'downloadUrl' => $downloadUrl,
            ], function ($message) use ($recipient, $subject) {
                $message->to($recipient)
                    ->subject($subject);
            });
        } catch (Throwable $e) {
            Log::error('Trimitere GDPR esuata.', [
                'comanda_id' => $comanda->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return back()->with('warning', 'Trimiterea emailului a esuat.');
        }

        ComandaEmailLog::create([
            'comanda_id' => $comanda->id,
            'sent_by' => $request->user()?->id,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $bodyHtml,
            'type' => 'gdpr',
            'meta' => [
                'document' => 'gdpr',
            ],
        ]);

        return back()->with('success', 'Acordul GDPR a fost trimis pe email.');
    }

    private function ensureCanManageFacturi(?User $user): void
    {
        if (!$user || !$user->hasPermission('facturi.write')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function ensureCanViewFacturi(?User $user): void
    {
        if (!$user || !$user->hasAnyPermission(['facturi.view', 'facturi.write'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function ensureCanSendFacturiEmail(?User $user): void
    {
        if (!$user || !$user->hasPermission('facturi.email.send')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function buildComandaAjaxPayload(Comanda $comanda, string $message): array
    {
        $comanda->load([
            'produse.produs',
            'plati',
        ]);

        $metodePlata = MetodaPlata::options();
        $statusPlataOptions = StatusPlata::options();

        return [
            'message' => $message,
            'counts' => [
                'necesar' => $comanda->produse->count(),
                'plati' => $comanda->plati->count(),
            ],
            'produse_html' => view('comenzi.partials.necesar-table-body', [
                'comanda' => $comanda,
            ])->render(),
            'plati_html' => view('comenzi.partials.plati-table-body', [
                'comanda' => $comanda,
                'metodePlata' => $metodePlata,
            ])->render(),
            'plati_summary_html' => view('comenzi.partials.plati-summary', [
                'comanda' => $comanda,
                'statusPlataOptions' => $statusPlataOptions,
            ])->render(),
        ];
    }

    private function storeSolicitariFromRequest(Request $request, Comanda $comanda, ?int $creatorId = null, ?string $creatorLabel = null): int
    {
        $creatorId = $creatorId ?? $request->user()?->id;
        $rows = $this->normalizeSolicitariInput($request->input('solicitari', []));

        $count = 0;
        foreach ($rows as $row) {
            $comanda->solicitari()->create([
                'solicitare_client' => $row['solicitare_client'],
                'cantitate' => $row['cantitate'],
                'created_by' => $creatorId,
                'created_by_label' => $creatorLabel,
            ]);
            $count++;
        }

        return $count;
    }

    private function normalizeSolicitariInput(array $solicitari): array
    {
        return collect($solicitari)
            ->map(function ($entry) {
                $solicitare = trim((string) ($entry['solicitare_client'] ?? ''));
                $cantitateValue = $entry['cantitate'] ?? null;
                $cantitate = $cantitateValue === '' || $cantitateValue === null ? null : (int) $cantitateValue;

                return [
                    'solicitare_client' => $solicitare !== '' ? $solicitare : null,
                    'cantitate' => $cantitate,
                ];
            })
            ->filter(fn ($entry) => $entry['solicitare_client'] !== null || $entry['cantitate'] !== null)
            ->values()
            ->all();
    }

    private function storeNotesFromRequest(Request $request, Comanda $comanda, string $role, ?int $creatorId = null, ?string $creatorLabel = null): int
    {
        $creatorId = $creatorId ?? $request->user()?->id;
        $rows = $this->normalizeNotesInput($request->input('note_entries', []));

        $count = 0;
        foreach ($rows as $row) {
            $comanda->note()->create([
                'role' => $role,
                'nota' => $row['nota'],
                'created_by' => $creatorId,
                'created_by_label' => $creatorLabel,
            ]);
            $count++;
        }

        return $count;
    }

    private function normalizeNotesInput(array $notes): array
    {
        return collect($notes)
            ->map(function ($entry) {
                $text = trim((string) ($entry['nota'] ?? ''));

                return [
                    'nota' => $text !== '' ? $text : null,
                ];
            })
            ->filter(fn ($entry) => $entry['nota'] !== null)
            ->values()
            ->all();
    }

    private function normalizeNoteRole(?string $role): ?string
    {
        if (!$role) {
            return null;
        }

        $role = strtolower(trim($role));
        return in_array($role, ['frontdesk', 'grafician', 'executant'], true) ? $role : null;
    }

    private function ensureCanEditNoteRole(Comanda $comanda, ?User $user, string $role): void
    {
        $allowed = match ($role) {
            'frontdesk' => $comanda->canEditNotaFrontdesk($user),
            'grafician' => $comanda->canEditNotaGrafician($user),
            'executant' => $comanda->canEditNotaExecutant($user),
            default => false,
        };

        if (!$allowed) {
            abort(403, 'Unauthorized action.');
        }
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
        $dueSoon = $request->boolean('due_soon');
        $asignateMie = $request->boolean('asignate_mie');
        $inAsteptare = $request->boolean('in_asteptare');
        $inAsteptareAll = $request->boolean('in_asteptare_all');
        $sort = $request->get('sort');
        $dir = strtolower($request->get('dir', 'asc'));
        $dir = $dir === 'desc' ? 'desc' : 'asc';
        $currentUserId = auth()->id();

        $query = Comanda::query()
            ->with([
            'client',
            'produse.produs',
            'facturi' => fn ($query) => $query->latest(),
            'facturi.uploadedBy',
            'facturaEmails' => fn ($query) => $query->latest(),
            'facturaEmails.sentBy',
        ])
            ->withCount(['facturi', 'facturaEmails', 'ofertaEmails', 'emailLogs', 'smsMessages'])
            ->when($tip, fn ($query) => $query->where('tip', $tip))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($sursa, fn ($query) => $query->where('sursa', $sursa))
            ->when($client, function ($query, $client) {
                $query->whereHas('client', function ($query) use ($client) {
                    $query->where('nume', 'like', '%' . $client . '%')
                        ->orWhere('telefon', 'like', '%' . $client . '%')
                        ->orWhere('telefon_secundar', 'like', '%' . $client . '%')
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
            ->when($dueSoon, fn ($query) => $query->dueSoon())
            ->when($asignateMie, fn ($query) => $query->assignedTo(auth()->id()));

        if ($inAsteptareAll) {
            $query->whereHas('etapaAssignments', function ($query) {
                $query->where('status', ComandaEtapaUser::STATUS_PENDING);
            });
        } elseif ($inAsteptare) {
            if (!$currentUserId) {
                $query->whereRaw('1=0');
            } else {
                $query->whereHas('etapaAssignments', function ($query) use ($currentUserId) {
                    $query->where('user_id', $currentUserId)
                        ->where('status', ComandaEtapaUser::STATUS_PENDING);
                });
            }
        }

        if ($currentUserId) {
            $query->withCount([
                'etapaAssignments as pending_etapa_assignments_count' => function ($query) use ($currentUserId) {
                    $query->where('user_id', $currentUserId)
                        ->where('status', ComandaEtapaUser::STATUS_PENDING);
                },
            ]);
        }

        $sortMap = [
            'client' => 'client',
            'tip' => 'comenzi.tip',
            'status' => 'comenzi.status',
            'sursa' => 'comenzi.sursa',
            'solicitare' => 'comenzi.data_solicitarii',
            'livrare' => 'comenzi.timp_estimat_livrare',
            'total' => 'comenzi.total',
            'plata' => 'comenzi.status_plata',
        ];

        if ($sort && array_key_exists($sort, $sortMap)) {
            if ($sort === 'client') {
                $query->orderBy(
                    Client::select('nume')
                        ->whereColumn('clienti.id', 'comenzi.client_id'),
                    $dir
                );
            } else {
                $query->orderBy($sortMap[$sort], $dir);
            }
        } else {
            $query->orderBy('data_solicitarii');
        }

        $comenzi = $query->simplePaginate(25);

        $tipuri = TipComanda::options();
        $surse = SursaComanda::options();
        $statusuri = StatusComanda::options();

        $emailTemplates = EmailTemplate::query()
            ->active()
            ->orderBy('name')
            ->get();
        if ($emailTemplates->isEmpty()) {
            $emailTemplates = EmailTemplate::query()->orderBy('name')->get();
        }

        return view('comenzi.index', [
            'comenzi' => $comenzi,
            'tip' => $tip,
            'status' => $status,
            'sursa' => $sursa,
            'client' => $client,
            'dataDe' => $dataDe,
            'dataPana' => $dataPana,
            'overdue' => $overdue,
            'dueSoon' => $dueSoon,
            'asignateMie' => $asignateMie,
            'inAsteptare' => $inAsteptare,
            'inAsteptareAll' => $inAsteptareAll,
            'sort' => $sort,
            'dir' => $dir,
            'tipuri' => $tipuri,
            'surse' => $surse,
            'statusuri' => $statusuri,
            'pageTitle' => $pageTitle,
            'fixedTip' => $fixedTip,
            'emailTemplates' => $emailTemplates,
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
