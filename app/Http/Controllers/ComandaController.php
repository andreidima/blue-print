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
use App\Models\NomenclatorProdusCustom;
use App\Models\Plata;
use App\Models\Produs;
use App\Models\User;
use App\Support\EmailContent;
use App\Support\EmailPlaceholders;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            'duplicate',
            'bulkDestroy',
            'restore',
            'bulkRestore',
            'forceDelete',
            'bulkForceDelete',
            'storeSolicitari',
            'destroySolicitare',
            'updateSolicitare',
            'storeNote',
            'updateNote',
            'destroyNote',
            'storeGdprConsent',
        ]);
        $this->middleware('checkUserPermission:comenzi.produse.write')->only(['storeProdus', 'updateProdus', 'destroyProdus', 'customProductNomenclatorOptions']);
        $this->middleware('checkUserPermission:comenzi.atasamente.write')->only(['storeAtasament', 'destroyAtasament']);
        $this->middleware('checkUserPermission:comenzi.mockupuri.write')->only(['storeMockup', 'destroyMockup']);
        $this->middleware('checkUserPermission:comenzi.plati.write')->only(['storePlata', 'updatePlata', 'destroyPlata']);
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
        return $this->listComenzi($request, TipComanda::ComandaFerma->value, 'Comenzi');
    }

    public function cereriOferta(Request $request)
    {
        return $this->listComenzi($request, TipComanda::CerereOferta->value, 'Cereri oferta');
    }

    public function trash(Request $request)
    {
        return $this->listComenzi($request, TipComanda::ComandaFerma->value, 'Comenzi - Trash', true);
    }

    public function cereriOfertaTrash(Request $request)
    {
        return $this->listComenzi($request, TipComanda::CerereOferta->value, 'Cereri oferta - Trash', true);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $this->rememberReturnUrl($request);

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
            'valabilitate_oferta' => ['nullable', 'date'],
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
        $this->rememberReturnUrl($request);
        $currentUser = $request->user();

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

        $activeUsers = User::query()
            ->with('roles')
            ->where('activ', true)
            ->withoutActiveRoles(['superadmin'])
            ->visibleTo($currentUser)
            ->orderBy('name')
            ->get();
        $activeUsers = $activeUsers
            ->push($comanda->supervizorUser)
            ->filter(function (?User $candidate) use ($currentUser) {
                if (!$candidate || $candidate->isSuperAdmin()) {
                    return false;
                }

                return $currentUser?->canSeeUser($candidate) ?? false;
            })
            ->unique('id')
            ->sortBy('name')
            ->values();
        $activeUsers->loadMissing('roles');

        $today = now((string) config('app.timezone', 'UTC'))->toDateString();
        $activeUsersByRole = $activeUsers
            ->map(function (User $user) use ($today) {
                $activeRoles = $user->roles
                    ->filter(function ($role) use ($today) {
                        $start = $role->pivot?->starts_at
                            ? Carbon::parse((string) $role->pivot->starts_at, (string) config('app.timezone', 'UTC'))->toDateString()
                            : null;
                        $end = $role->pivot?->ends_at
                            ? Carbon::parse((string) $role->pivot->ends_at, (string) config('app.timezone', 'UTC'))->toDateString()
                            : null;

                        if ($start !== null && $start > $today) {
                            return false;
                        }

                        if ($end !== null && $end < $today) {
                            return false;
                        }

                        return true;
                    })
                    ->sortBy('name')
                    ->values();

                $primaryRole = $activeRoles->first();

                return [
                    'role_slug' => $primaryRole?->slug ?? 'fara-rol',
                    'role_name' => $primaryRole?->name ?? 'Fara rol activ',
                    'user' => $user,
                ];
            })
            ->groupBy('role_slug')
            ->map(function ($items) {
                return [
                    'slug' => (string) ($items->first()['role_slug'] ?? 'fara-rol'),
                    'name' => (string) ($items->first()['role_name'] ?? 'Fara rol activ'),
                    'users' => $items->pluck('user')->sortBy('name')->values(),
                ];
            })
            ->sortBy(function (array $roleGroup) {
                $priorityByRoleSlug = [
                    'supervizor' => 10,
                    'operator-front-office' => 20,
                    'operator-tipografie' => 30,
                    'grafician' => 40,
                    'financiar' => 50,
                ];
                $priority = $priorityByRoleSlug[$roleGroup['slug']] ?? 999;

                return sprintf('%03d-%s', $priority, strtolower((string) ($roleGroup['name'] ?? '')));
            })
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
            'activeUsersByRole',
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
        $isCerereOferta = $this->isCerereOferta($comanda);

        $rules = [
            'client_id' => ['sometimes', 'required', 'exists:clienti,id'],
            'tip' => ['required', Rule::in(array_keys(TipComanda::options()))],
            'sursa' => ['required', Rule::in(array_keys(SursaComanda::options()))],
            'status' => ['required', Rule::in(array_keys(StatusComanda::options()))],
            'data_solicitarii' => ['required', 'date'],
            'valabilitate_oferta' => ['nullable', 'date'],
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

        if ($isCerereOferta) {
            $data['necesita_tipar_exemplu'] = (bool) $comanda->necesita_tipar_exemplu;
            $data['necesita_mockup'] = (bool) $comanda->necesita_mockup;
        } else {
            $data['necesita_tipar_exemplu'] = $request->boolean('necesita_tipar_exemplu');
            $data['necesita_mockup'] = $request->boolean('necesita_mockup');
        }

        if (in_array($data['status'], StatusComanda::finalStates(), true)) {
            $data['finalizat_la'] = $comanda->finalizat_la ?? now();
        } else {
            $data['finalizat_la'] = null;
        }

        $comanda->update($data);

        if ($comanda->canEditAssignments($user)) {
            $etapeInput = $request->input('etape', []);
            $etapaSlugById = Etapa::pluck('slug', 'id')
                ->map(fn ($slug) => (string) $slug)
                ->all();
            $etapaIds = array_keys($etapaSlugById);
            $assignableUserIds = User::query()
                ->where('activ', true)
                ->withoutActiveRoles(['superadmin'])
                ->visibleTo($user)
                ->pluck('id')
                ->all();
            if ($comanda->supervizor_user_id) {
                $supervizor = User::find($comanda->supervizor_user_id);
                if ($supervizor && !$supervizor->isSuperAdmin() && $user->canSeeUser($supervizor)) {
                    $assignableUserIds[] = $comanda->supervizor_user_id;
                }
            }
            $assignableUserIds = array_map('intval', $assignableUserIds);
            $assignableUserIds = array_values(array_unique($assignableUserIds));

            foreach ($etapaIds as $etapaId) {
                $etapaSlug = $etapaSlugById[$etapaId] ?? null;
                if ($isCerereOferta && $etapaSlug !== 'preluare_comanda') {
                    continue;
                }

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

        $message = 'Comanda a fost actualizata cu succes!';
        if ($request->wantsJson()) {
            return response()->json(
                $this->buildComandaAjaxPayload($request, $comanda, $message, ['detalii'])
            );
        }

        return back()->with('status', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Comanda $comanda)
    {
        $comanda->delete();

        return back()->with('status', 'Comanda a fost mutata in trash.');
    }

    public function duplicate(Request $request, Comanda $comanda)
    {
        $creatorId = $request->user()?->id;

        $duplicated = DB::transaction(function () use ($comanda, $creatorId) {
            $comanda->load([
                'produse',
                'solicitari',
                'etapaAssignments',
            ]);

            $today = now()->startOfDay();
            $valabilitateOferta = null;
            if ($comanda->valabilitate_oferta) {
                if ($comanda->data_solicitarii) {
                    $validityOffsetInDays = $comanda->data_solicitarii->diffInDays($comanda->valabilitate_oferta, false);
                    $valabilitateOferta = $today->copy()->addDays($validityOffsetInDays);
                } else {
                    $valabilitateOferta = $comanda->valabilitate_oferta;
                }
            }

            $copie = Comanda::create([
                'client_id' => $comanda->client_id,
                'woocommerce_order_id' => null,
                'tip' => $comanda->tip,
                'sursa' => $comanda->sursa,
                'status' => StatusComanda::Nou->value,
                'data_solicitarii' => $today->toDateString(),
                'valabilitate_oferta' => $valabilitateOferta?->toDateString(),
                'timp_estimat_livrare' => $comanda->timp_estimat_livrare,
                'finalizat_la' => null,
                'necesita_tipar_exemplu' => (bool) $comanda->necesita_tipar_exemplu,
                'necesita_mockup' => (bool) $comanda->necesita_mockup,
                'adresa_facturare' => $comanda->adresa_facturare,
                'adresa_livrare' => $comanda->adresa_livrare,
                'awb' => $comanda->awb,
                'frontdesk_user_id' => $comanda->frontdesk_user_id,
                'supervizor_user_id' => $comanda->supervizor_user_id,
                'grafician_user_id' => $comanda->grafician_user_id,
                'executant_user_id' => $comanda->executant_user_id,
                'total' => 0,
                'total_platit' => 0,
                'status_plata' => StatusPlata::Neplatit->value,
            ]);

            foreach ($comanda->produse as $linie) {
                $copie->produse()->create([
                    'produs_id' => $linie->produs_id,
                    'custom_denumire' => $linie->custom_denumire,
                    'descriere' => $linie->descriere,
                    'cantitate' => $linie->cantitate,
                    'pret_unitar' => $linie->pret_unitar,
                    'total_linie' => $linie->total_linie,
                ]);
            }

            foreach ($comanda->solicitari as $solicitare) {
                $copie->solicitari()->create([
                    'solicitare_client' => $solicitare->solicitare_client,
                    'cantitate' => $solicitare->cantitate,
                    'created_by' => $creatorId ?? $solicitare->created_by,
                    'created_by_label' => $solicitare->created_by_label,
                ]);
            }

            $addedAssignments = [];
            foreach ($comanda->etapaAssignments as $assignment) {
                if (!$assignment->etapa_id || !$assignment->user_id) {
                    continue;
                }

                $dedupeKey = $assignment->etapa_id . ':' . $assignment->user_id;
                if (isset($addedAssignments[$dedupeKey])) {
                    continue;
                }

                $copie->etapaAssignments()->create([
                    'etapa_id' => $assignment->etapa_id,
                    'user_id' => $assignment->user_id,
                    'status' => ComandaEtapaUser::STATUS_PENDING,
                ]);
                $addedAssignments[$dedupeKey] = true;
            }

            $copie->recalculateTotals();

            return $copie;
        });

        $itemLabel = $duplicated->tip === TipComanda::CerereOferta->value
            ? 'Cererea de oferta'
            : 'Comanda';

        return redirect()
            ->route('comenzi.show', $duplicated)
            ->with('success', "{$itemLabel} a fost duplicata.");
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'comanda_ids' => ['required', 'array', 'min:1'],
            'comanda_ids.*' => ['required', 'integer', 'exists:comenzi,id'],
        ]);

        $ids = collect($data['comanda_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors('Selecteaza cel putin o comanda.');
        }

        $deletedCount = Comanda::query()->whereIn('id', $ids)->delete();

        if ($deletedCount <= 0) {
            return back()->withErrors('Nu s-a putut muta nicio comanda in trash.');
        }

        $message = $deletedCount === 1
            ? 'Comanda selectata a fost mutata in trash.'
            : "Cele {$deletedCount} comenzi selectate au fost mutate in trash.";

        return back()->with('status', $message);
    }

    public function restore(int $comandaId)
    {
        $comanda = Comanda::onlyTrashed()->findOrFail($comandaId);
        $comanda->restore();

        return back()->with('status', 'Comanda a fost restaurata din trash.');
    }

    public function bulkRestore(Request $request)
    {
        $data = $request->validate([
            'comanda_ids' => ['required', 'array', 'min:1'],
            'comanda_ids.*' => ['required', 'integer', 'exists:comenzi,id'],
        ]);

        $ids = collect($data['comanda_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors('Selecteaza cel putin o comanda.');
        }

        $restoredCount = Comanda::onlyTrashed()->whereIn('id', $ids)->restore();
        if ($restoredCount <= 0) {
            return back()->withErrors('Nu s-a putut restaura nicio comanda.');
        }

        $message = $restoredCount === 1
            ? 'Comanda selectata a fost restaurata.'
            : "Cele {$restoredCount} comenzi selectate au fost restaurate.";

        return back()->with('status', $message);
    }

    public function forceDelete(int $comandaId)
    {
        $comanda = Comanda::onlyTrashed()->findOrFail($comandaId);

        try {
            $comanda->forceDelete();
        } catch (Throwable $e) {
            Log::warning('Stergere definitiva comanda esuata.', [
                'comanda_id' => $comandaId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', 'Comanda nu a putut fi stearsa definitiv.');
        }

        return back()->with('status', 'Comanda a fost stearsa definitiv.');
    }

    public function bulkForceDelete(Request $request)
    {
        $data = $request->validate([
            'comanda_ids' => ['required', 'array', 'min:1'],
            'comanda_ids.*' => ['required', 'integer', 'exists:comenzi,id'],
        ]);

        $ids = collect($data['comanda_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors('Selecteaza cel putin o comanda.');
        }

        $comenzi = Comanda::onlyTrashed()->whereIn('id', $ids)->get();
        $deletedCount = 0;
        $failedCount = 0;

        foreach ($comenzi as $comanda) {
            try {
                $comanda->forceDelete();
                $deletedCount++;
            } catch (Throwable $e) {
                $failedCount++;
                Log::warning('Stergere definitiva comanda esuata.', [
                    'comanda_id' => $comanda->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($deletedCount <= 0 && $failedCount > 0) {
            return back()->with('warning', 'Comenzile selectate nu au putut fi sterse definitiv.');
        }

        if ($deletedCount <= 0) {
            return back()->withErrors('Nu s-a putut sterge definitiv nicio comanda.');
        }

        $message = $deletedCount === 1
            ? 'Comanda selectata a fost stearsa definitiv.'
            : "Cele {$deletedCount} comenzi selectate au fost sterse definitiv.";

        $response = back()->with('status', $message);
        if ($failedCount > 0) {
            $response->with('warning', $failedCount === 1
                ? 'O comanda nu a putut fi stearsa definitiv.'
                : "{$failedCount} comenzi nu au putut fi sterse definitiv.");
        }

        return $response;
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
            return $this->respondWithComandaPayload(
                $request,
                $comanda,
                'Nu exista informatii de salvat.',
                ['solicitari'],
                'warning'
            );
        }

        $message = $added === 1
            ? 'Informatiile comenzii au fost adaugate.'
            : "Au fost adaugate {$added} informatii pe comanda.";

        return $this->respondWithComandaPayload($request, $comanda, $message, ['solicitari']);
    }

    public function destroySolicitare(Request $request, Comanda $comanda, ComandaSolicitare $solicitare)
    {
        $user = $request->user();
        abort_unless($comanda->canEditNotaFrontdesk($user), 403);
        abort_unless($solicitare->comanda_id === $comanda->id, 404);

        if ($response = $this->denyIfDailySectionEditLocked(
            $request,
            $comanda,
            $solicitare->created_at,
            ['solicitari'],
            'Informatiile comenzii nu mai pot fi sterse.'
        )) {
            return $response;
        }

        $solicitare->delete();

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            'Informatiile comenzii au fost sterse.',
            ['solicitari']
        );
    }

    public function updateSolicitare(Request $request, Comanda $comanda, ComandaSolicitare $solicitare)
    {
        $user = $request->user();
        abort_unless($comanda->canEditNotaFrontdesk($user), 403);
        abort_unless($solicitare->comanda_id === $comanda->id, 404);

        if ($response = $this->denyIfDailySectionEditLocked(
            $request,
            $comanda,
            $solicitare->created_at,
            ['solicitari'],
            'Informatiile comenzii nu mai pot fi editate.'
        )) {
            return $response;
        }

        $data = $request->validate([
            'solicitare_client' => ['nullable', 'string'],
            'cantitate' => ['nullable', 'integer', 'min:1'],
        ]);

        $solicitare->update($data);

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            'Solicitarea a fost actualizata.',
            ['solicitari']
        );
    }

    public function storeNote(Request $request, Comanda $comanda, string $role)
    {
        $user = $request->user();
        $role = $this->normalizeNoteRole($role);
        if (!$role) {
            abort(404);
        }

        if ($response = $this->denyIfCerereOfertaNoteRoleLocked($request, $comanda, $role)) {
            return $response;
        }

        $this->ensureCanEditNoteRole($comanda, $user, $role);

        $request->validate([
            'note_entries' => ['nullable', 'array'],
            'note_entries.*.nota' => ['nullable', 'string'],
        ]);

        $added = $this->storeNotesFromRequest($request, $comanda, $role);
        if ($added === 0) {
            return $this->respondWithComandaPayload(
                $request,
                $comanda,
                'Nu exista note de salvat.',
                ['note'],
                'warning'
            );
        }

        $message = $added === 1
            ? 'Nota a fost adaugata.'
            : "Au fost adaugate {$added} note.";

        return $this->respondWithComandaPayload($request, $comanda, $message, ['note']);
    }

    public function updateNote(Request $request, Comanda $comanda, ComandaNota $nota)
    {
        $user = $request->user();
        abort_unless($nota->comanda_id === $comanda->id, 404);

        $role = $this->normalizeNoteRole($nota->role);
        if (!$role) {
            abort(404);
        }

        if ($response = $this->denyIfCerereOfertaNoteRoleLocked($request, $comanda, $role)) {
            return $response;
        }

        $this->ensureCanEditNoteRole($comanda, $user, $role);

        if ($response = $this->denyIfDailySectionEditLocked(
            $request,
            $comanda,
            $nota->created_at,
            ['note'],
            'Nota nu mai poate fi editata.'
        )) {
            return $response;
        }

        $data = $request->validate([
            'nota' => ['nullable', 'string'],
        ]);

        $text = trim((string) ($data['nota'] ?? ''));
        if ($text === '') {
            return $this->respondWithComandaPayload(
                $request,
                $comanda,
                'Nota nu poate fi goala.',
                ['note'],
                'warning'
            );
        }

        $nota->update(['nota' => $text]);

        return $this->respondWithComandaPayload($request, $comanda, 'Nota a fost actualizata.', ['note']);
    }

    public function destroyNote(Request $request, Comanda $comanda, ComandaNota $nota)
    {
        $user = $request->user();
        abort_unless($nota->comanda_id === $comanda->id, 404);

        $role = $this->normalizeNoteRole($nota->role);
        if (!$role) {
            abort(404);
        }

        if ($response = $this->denyIfCerereOfertaNoteRoleLocked($request, $comanda, $role)) {
            return $response;
        }

        $this->ensureCanEditNoteRole($comanda, $user, $role);

        if ($response = $this->denyIfDailySectionEditLocked(
            $request,
            $comanda,
            $nota->created_at,
            ['note'],
            'Nota nu mai poate fi stearsa.'
        )) {
            return $response;
        }

        $nota->delete();

        return $this->respondWithComandaPayload($request, $comanda, 'Nota a fost stearsa.', ['note']);
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
                'custom_descriere' => ['nullable', 'string', 'max:1000'],
                'custom_denumire' => ['required', 'string', 'max:255'],
                'custom_nomenclator_id' => ['nullable', 'integer', 'exists:nomenclator_produse_custom,id'],
                'custom_add_to_nomenclator' => ['nullable', 'boolean'],
                'update_custom_description_default' => ['nullable', 'boolean'],
                'custom_pret_unitar' => ['required', 'numeric', 'min:0'],
                'cantitate' => ['required', 'integer', 'min:1'],
            ]);

            $pretUnitar = round((float) $data['custom_pret_unitar'], 2);
            $totalLinie = round($pretUnitar * $data['cantitate'], 2);
            $lineDescription = trim((string) ($data['custom_descriere'] ?? '')) ?: null;
            $resolved = $this->resolveCustomProductNameFromNomenclator(
                trim((string) $data['custom_denumire']),
                isset($data['custom_nomenclator_id']) ? (int) $data['custom_nomenclator_id'] : null,
                $request->boolean('custom_add_to_nomenclator'),
                $request->user()?->id
            );

            $comanda->produse()->create([
                'produs_id' => null,
                'custom_denumire' => $resolved['denumire'],
                'descriere' => $lineDescription,
                'cantitate' => $data['cantitate'],
                'pret_unitar' => $pretUnitar,
                'total_linie' => $totalLinie,
            ]);

            $canonicalNomenclatorId = $resolved['canonical_nomenclator_id'] ?? null;
            if ($canonicalNomenclatorId) {
                $nomenclatorEntry = NomenclatorProdusCustom::query()->find($canonicalNomenclatorId);
                if ($nomenclatorEntry) {
                    $shouldPersistDefaultDescription = $request->boolean('update_custom_description_default')
                        || ($nomenclatorEntry->descriere === null && $lineDescription !== null);

                    if ($shouldPersistDefaultDescription && $nomenclatorEntry->descriere !== $lineDescription) {
                        $nomenclatorEntry->update(['descriere' => $lineDescription]);
                    }
                }
            }
        } else {
            $data = $request->validate([
                'produs_tip' => ['nullable', Rule::in(['existing', 'custom'])],
                'produs_id' => ['required', 'exists:produse,id'],
                'descriere' => ['nullable', 'string', 'max:1000'],
                'update_product_description_default' => ['nullable', 'boolean'],
                'cantitate' => ['required', 'integer', 'min:1'],
            ]);

            $produs = Produs::findOrFail($data['produs_id']);
            $totalLinie = round($produs->pret * $data['cantitate'], 2);
            $lineDescription = trim((string) ($data['descriere'] ?? '')) ?: null;

            $comanda->produse()->create([
                'produs_id' => $produs->id,
                'descriere' => $lineDescription,
                'cantitate' => $data['cantitate'],
                'pret_unitar' => $produs->pret,
                'total_linie' => $totalLinie,
            ]);

            $shouldPersistDefaultDescription = $request->boolean('update_product_description_default')
                || ($produs->descriere === null && $lineDescription !== null);
            if ($shouldPersistDefaultDescription && $produs->descriere !== $lineDescription) {
                $produs->update(['descriere' => $lineDescription]);
            }
        }

        $comanda->recalculateTotals();

        $message = $produsTip === 'custom'
            ? 'Produsul custom a fost adaugat pe comanda.'
            : 'Produsul a fost adaugat pe comanda.';
        if ($produsTip === 'custom' && ($resolved['added_to_nomenclator'] ?? false)) {
            $message .= ' Produsul a fost adaugat si in nomenclator.';
        }

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            $message,
            ['necesar', 'plati']
        );
    }

    public function customProductNomenclatorOptions(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'id' => ['nullable', 'integer', 'exists:nomenclator_produse_custom,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $limit = $data['limit'] ?? 12;
        $page = $data['page'] ?? 1;

        if (!empty($data['id'])) {
            $entry = NomenclatorProdusCustom::findOrFail((int) $data['id']);
            $canonical = $this->resolveCanonicalCustomProductEntry($entry);
            if (!$canonical) {
                return response()->json(['results' => []]);
            }

            return response()->json([
                'results' => [[
                    'id' => $canonical->id,
                    'label' => $canonical->denumire,
                    'descriere' => $canonical->descriere,
                ]],
            ]);
        }

        $search = trim((string) ($data['search'] ?? ''));

        $query = NomenclatorProdusCustom::query()
            ->canonical()
            ->orderBy('denumire');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('denumire', 'like', '%' . $search . '%');

                $lookup = NomenclatorProdusCustom::makeLookupKey($search);
                if ($lookup !== '') {
                    $builder->orWhere('lookup_key', 'like', '%' . $lookup . '%');
                }
            });
        }

        $paginator = $query->simplePaginate($limit, ['*'], 'page', $page);

        return response()->json([
            'results' => $paginator->getCollection()->map(fn (NomenclatorProdusCustom $entry) => [
                'id' => $entry->id,
                'label' => $entry->denumire,
                'descriere' => $entry->descriere,
            ])->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function storeAtasament(Request $request, Comanda $comanda)
    {
        if ($response = $this->denyIfCerereOfertaSectionLocked(
            $request,
            $comanda,
            ['fisiere'],
            'Sectiunea fisiere este blocata pentru cererile de oferta.'
        )) {
            return $response;
        }

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

        return $this->respondWithComandaPayload($request, $comanda, $message, ['fisiere']);
    }

    public function storeFactura(Request $request, Comanda $comanda)
    {
        $this->ensureCanManageFacturi($request->user());

        if ($response = $this->denyIfCerereOfertaSectionLocked(
            $request,
            $comanda,
            ['fisiere'],
            'Sectiunea fisiere este blocata pentru cererile de oferta.'
        )) {
            return $response;
        }

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

        return $this->respondWithComandaPayload($request, $comanda, $message, ['fisiere']);
    }

    public function storeMockup(Request $request, Comanda $comanda)
    {
        if ($response = $this->denyIfCerereOfertaSectionLocked(
            $request,
            $comanda,
            ['fisiere'],
            'Sectiunea fisiere este blocata pentru cererile de oferta.'
        )) {
            return $response;
        }

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

        return $this->respondWithComandaPayload($request, $comanda, $message, ['fisiere']);
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

    public function destroyAtasament(Request $request, Comanda $comanda, ComandaAtasament $atasament)
    {
        abort_unless($atasament->comanda_id === $comanda->id, 404);

        if ($response = $this->denyIfCerereOfertaSectionLocked(
            $request,
            $comanda,
            ['fisiere'],
            'Sectiunea fisiere este blocata pentru cererile de oferta.'
        )) {
            return $response;
        }

        if ($response = $this->denyIfDailySectionEditLocked(
            $request,
            $comanda,
            $atasament->created_at,
            ['fisiere'],
            'Fisierul nu mai poate fi sters.'
        )) {
            return $response;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($atasament->path)) {
            $disk->delete($atasament->path);
        }

        $atasament->delete();

        return $this->respondWithComandaPayload($request, $comanda, 'Atasamentul a fost sters.', ['fisiere']);
    }

    public function viewFactura(Request $request, Comanda $comanda, ComandaFactura $factura)
    {
        $this->ensureCanViewFacturi($request->user());
        $this->ensureCanOperateFacturaFiles($request->user());
        abort_unless($factura->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($factura->path), 404);

        return $disk->response($factura->path, $factura->original_name, [], 'inline');
    }

    public function downloadFactura(Request $request, Comanda $comanda, ComandaFactura $factura)
    {
        $this->ensureCanViewFacturi($request->user());
        $this->ensureCanOperateFacturaFiles($request->user());
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
        $this->ensureCanOperateFacturaFiles($request->user());
        abort_unless($factura->comanda_id === $comanda->id, 404);

        if ($response = $this->denyIfCerereOfertaSectionLocked(
            $request,
            $comanda,
            ['fisiere'],
            'Sectiunea fisiere este blocata pentru cererile de oferta.'
        )) {
            return $response;
        }

        if ($response = $this->denyIfDailySectionEditLocked(
            $request,
            $comanda,
            $factura->created_at,
            ['fisiere'],
            'Factura nu mai poate fi stearsa.'
        )) {
            return $response;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($factura->path)) {
            $disk->delete($factura->path);
        }

        $factura->delete();

        return $this->respondWithComandaPayload($request, $comanda, 'Factura a fost stearsa.', ['fisiere']);
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

    public function downloadMockupPublic(Comanda $comanda, Mockup $mockup)
    {
        abort_unless($mockup->comanda_id === $comanda->id, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($mockup->path), 404);

        return $disk->download($mockup->path, $mockup->original_name);
    }

    public function destroyMockup(Request $request, Comanda $comanda, Mockup $mockup)
    {
        abort_unless($mockup->comanda_id === $comanda->id, 404);

        if ($response = $this->denyIfCerereOfertaSectionLocked(
            $request,
            $comanda,
            ['fisiere'],
            'Sectiunea fisiere este blocata pentru cererile de oferta.'
        )) {
            return $response;
        }

        if ($response = $this->denyIfDailySectionEditLocked(
            $request,
            $comanda,
            $mockup->created_at,
            ['fisiere'],
            'Fisierul info nu mai poate fi sters.'
        )) {
            return $response;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($mockup->path)) {
            $disk->delete($mockup->path);
        }

        $mockup->delete();

        return $this->respondWithComandaPayload($request, $comanda, 'Mockup-ul a fost sters.', ['fisiere']);
    }

    public function storePlata(Request $request, Comanda $comanda)
    {
        if ($response = $this->denyIfCerereOfertaSectionLocked(
            $request,
            $comanda,
            ['plati'],
            'Sectiunea plati este blocata pentru cererile de oferta.'
        )) {
            return $response;
        }

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

        return $this->respondWithComandaPayload($request, $comanda, $message, ['plati']);
    }

    public function updateProdus(Request $request, Comanda $comanda, ComandaProdus $linie)
    {
        abort_unless($linie->comanda_id === $comanda->id, 404);

        $data = $request->validate([
            'descriere' => ['nullable', 'string', 'max:1000'],
            'cantitate' => ['required', 'integer', 'min:1'],
            'pret_unitar' => ['required', 'numeric', 'min:0'],
        ]);

        $cantitate = (int) $data['cantitate'];
        $pretUnitar = round((float) $data['pret_unitar'], 2);

        $linie->update([
            'descriere' => trim((string) ($data['descriere'] ?? '')) ?: null,
            'cantitate' => $cantitate,
            'pret_unitar' => $pretUnitar,
            'total_linie' => round($cantitate * $pretUnitar, 2),
        ]);

        $comanda->recalculateTotals();

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            'Linia de produs a fost actualizata.',
            ['necesar', 'plati']
        );
    }

    public function destroyProdus(Request $request, Comanda $comanda, ComandaProdus $linie)
    {
        abort_unless($linie->comanda_id === $comanda->id, 404);

        $linie->delete();
        $comanda->recalculateTotals();

        $message = 'Produsul a fost eliminat.';

        return $this->respondWithComandaPayload($request, $comanda, $message, ['necesar', 'plati']);
    }

    public function updatePlata(Request $request, Comanda $comanda, Plata $plata)
    {
        abort_unless($plata->comanda_id === $comanda->id, 404);

        if ($response = $this->denyIfCerereOfertaSectionLocked(
            $request,
            $comanda,
            ['plati'],
            'Sectiunea plati este blocata pentru cererile de oferta.'
        )) {
            return $response;
        }

        $data = $request->validate([
            'suma' => ['required', 'numeric', 'min:0.01'],
            'metoda' => ['required', Rule::in(array_keys(MetodaPlata::options()))],
            'numar_factura' => ['nullable', 'string', 'max:50'],
            'platit_la' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $plata->update([
            'suma' => $data['suma'],
            'metoda' => $data['metoda'],
            'numar_factura' => $data['numar_factura'] ?? null,
            'platit_la' => Carbon::parse($data['platit_la']),
            'note' => $data['note'] ?? null,
        ]);

        $comanda->recalculateTotals();

        return $this->respondWithComandaPayload($request, $comanda, 'Plata a fost actualizata.', ['plati']);
    }

    public function destroyPlata(Request $request, Comanda $comanda, Plata $plata)
    {
        abort_unless($plata->comanda_id === $comanda->id, 404);

        if ($response = $this->denyIfCerereOfertaSectionLocked(
            $request,
            $comanda,
            ['plati'],
            'Sectiunea plati este blocata pentru cererile de oferta.'
        )) {
            return $response;
        }

        $plata->delete();
        $comanda->recalculateTotals();

        $message = 'Plata a fost eliminata.';

        return $this->respondWithComandaPayload($request, $comanda, $message, ['plati']);
    }

    public function approveAssignments(Request $request, Comanda $comanda)
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return back()->with('warning', 'Trebuie sa fii autentificat pentru a aproba cererea.');
        }

        $query = ComandaEtapaUser::where('comanda_id', $comanda->id)
            ->where('user_id', $userId)
            ->where('status', ComandaEtapaUser::STATUS_PENDING);
        if ($this->isCerereOferta($comanda)) {
            $preluareEtapaId = Etapa::where('slug', 'preluare_comanda')->value('id');
            if (!$preluareEtapaId) {
                return back()->with('warning', 'Etapa preluare comanda nu este configurata.');
            }

            $query->where('etapa_id', $preluareEtapaId);
        }

        $updated = $query->update(['status' => ComandaEtapaUser::STATUS_APPROVED]);

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
            'mockup_link_types' => ['nullable', 'array'],
            'mockup_link_types.*' => ['string', Rule::in(array_keys(Mockup::typeOptions()))],
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
        $mockupLinks = $this->resolveSelectedMockupLinks($comanda, $data['mockup_link_types'] ?? []);
        $downloadLinks = $facturi->map(function (ComandaFactura $factura) use ($comanda) {
            return [
                'label' => $factura->original_name ?: ("factura-{$factura->id}.pdf"),
                'url' => URL::temporarySignedRoute(
                    'comenzi.facturi.public-download',
                    now()->addDays(30),
                    ['comanda' => $comanda->id, 'factura' => $factura->id]
                ),
            ];
        })->values()->all();
        $downloadLinks = array_merge($downloadLinks, $mockupLinks['links']);

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
            'meta' => [
                'document' => 'factura',
                'info_links' => $mockupLinks['snapshot'],
            ],
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
        if ($this->isCerereOferta($comanda)) {
            abort(403, 'Fisa interna nu este disponibila pentru cererile de oferta.');
        }

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
        if ($this->isCerereOferta($comanda)) {
            abort(403, 'Procesul verbal nu este disponibil pentru cererile de oferta.');
        }

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
            'mockup_link_types' => ['nullable', 'array'],
            'mockup_link_types.*' => ['string', Rule::in(array_keys(Mockup::typeOptions()))],
        ]);

        $recipient = optional($comanda->client)->email;
        if (!$recipient) {
            return back()->with('warning', 'Clientul nu are un email setat.');
        }

        $comanda->load(['client', 'produse.produs']);

        $placeholders = EmailPlaceholders::forComanda($comanda);
        $subject = EmailContent::replacePlaceholders($data['subject'], $placeholders);
        $bodyHtml = EmailContent::formatBody($data['body'], $placeholders);
        $mockupLinks = $this->resolveSelectedMockupLinks($comanda, $data['mockup_link_types'] ?? []);
        $downloadUrl = URL::temporarySignedRoute(
            'comenzi.pdf.oferta.signed',
            now()->addDays(30),
            ['comanda' => $comanda->id]
        );
        $downloadLinks = array_merge([
            [
                'label' => "oferta-comanda-{$comanda->id}.pdf",
                'url' => $downloadUrl,
            ],
        ], $mockupLinks['links']);

        try {
            Mail::send('emails.comenzi.oferta', [
                'comanda' => $comanda,
                'bodyHtml' => $bodyHtml,
                'downloadLinks' => $downloadLinks,
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
            'meta' => [
                'document' => 'oferta',
                'info_links' => $mockupLinks['snapshot'],
            ],
        ]);

        return back()->with('success', 'Oferta PDF a fost trimisa pe email.');
    }

    public function storeGdprConsent(Request $request, Comanda $comanda)
    {
        $requiresSignature = $comanda->sursa === SursaComanda::Fizic->value;

        $data = $request->validate([
            'method' => ['nullable', Rule::in(['signature', 'checkbox'])],
            'consent_processing' => ['accepted'],
            'consent_marketing' => ['nullable', 'boolean'],
            'consent_media_marketing' => ['nullable', 'boolean'],
            'signature_data' => ['nullable', 'string'],
        ]);

        $method = $requiresSignature ? 'signature' : 'checkbox';
        $signaturePath = null;

        if ($requiresSignature) {
            $signatureData = $data['signature_data'] ?? null;
            if (!$signatureData || !Str::startsWith($signatureData, 'data:image/png;base64,')) {
                return $this->respondWithComandaPayload(
                    $request,
                    $comanda,
                    'Semnatura este invalida sau lipseste.',
                    ['gdpr'],
                    'warning'
                );
            }

            $signatureBinary = base64_decode(substr($signatureData, strlen('data:image/png;base64,')));
            if ($signatureBinary === false) {
                return $this->respondWithComandaPayload(
                    $request,
                    $comanda,
                    'Semnatura nu a putut fi procesata.',
                    ['gdpr'],
                    'warning'
                );
            }

            $path = "comenzi/{$comanda->id}/gdpr/semnatura-" . now()->format('YmdHis') . '-' . Str::random(6) . '.png';
            $stored = Storage::disk('public')->put($path, $signatureBinary);
            if (!$stored) {
                return $this->respondWithComandaPayload(
                    $request,
                    $comanda,
                    'Semnatura nu a putut fi salvata.',
                    ['gdpr'],
                    'warning'
                );
            }
            $signaturePath = $path;
        }

        $consentMarketing = $requiresSignature
            ? $request->boolean('consent_marketing')
            : true;
        $consentMediaMarketing = $requiresSignature
            ? $request->boolean('consent_media_marketing')
            : true;

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
            'consent_marketing' => $consentMarketing,
            'consent_media_marketing' => $consentMediaMarketing,
            'signature_path' => $signaturePath,
            'signed_at' => now(),
            'client_snapshot' => $clientSnapshot,
            'created_by' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            'Acordul GDPR a fost salvat.',
            ['gdpr']
        );
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
            'mockup_link_types' => ['nullable', 'array'],
            'mockup_link_types.*' => ['string', Rule::in(array_keys(Mockup::typeOptions()))],
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
        $mockupLinks = $this->resolveSelectedMockupLinks($comanda, $data['mockup_link_types'] ?? []);
        $downloadUrl = URL::temporarySignedRoute(
            'comenzi.pdf.gdpr.signed',
            now()->addDays(30),
            ['comanda' => $comanda->id]
        );
        $downloadLinks = array_merge([
            [
                'label' => "gdpr-comanda-{$comanda->id}.pdf",
                'url' => $downloadUrl,
            ],
        ], $mockupLinks['links']);

        try {
            Mail::send('emails.comenzi.gdpr', [
                'comanda' => $comanda,
                'bodyHtml' => $bodyHtml,
                'downloadLinks' => $downloadLinks,
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
                'info_links' => $mockupLinks['snapshot'],
            ],
        ]);

        return back()->with('success', 'Acordul GDPR a fost trimis pe email.');
    }

    private function resolveSelectedMockupLinks(Comanda $comanda, array $mockupTypes): array
    {
        $mockupTypes = $this->sanitizeMockupLinkTypes($mockupTypes);
        if ($mockupTypes === []) {
            return ['links' => [], 'snapshot' => []];
        }

        $includesInfoMockup = in_array(Mockup::TIP_INFO_MOCKUP, $mockupTypes, true);
        $otherTypes = array_values(array_filter($mockupTypes, fn (string $type) => $type !== Mockup::TIP_INFO_MOCKUP));

        $query = $comanda->mockupuri()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->where(function ($builder) use ($includesInfoMockup, $otherTypes) {
                if ($otherTypes !== []) {
                    $builder->whereIn('tip', $otherTypes);
                }

                if ($includesInfoMockup) {
                    $builder->orWhere('tip', Mockup::TIP_INFO_MOCKUP)
                        ->orWhereNull('tip');
                }
            });

        $disk = Storage::disk('public');
        $typeOptions = Mockup::typeOptions();
        $linksByType = [];
        $snapshotByType = [];

        foreach ($query->get() as $mockup) {
            $type = $mockup->tip ?: Mockup::TIP_INFO_MOCKUP;
            if (!in_array($type, $mockupTypes, true) || isset($linksByType[$type])) {
                continue;
            }

            if (!$mockup->path || !$disk->exists($mockup->path)) {
                continue;
            }

            $typeLabel = $typeOptions[$type] ?? 'Info';
            $fileLabel = $mockup->original_name ?: ('Fisier #' . $mockup->id);

            $linksByType[$type] = [
                'label' => $fileLabel,
                'url' => URL::temporarySignedRoute(
                    'comenzi.mockupuri.public-download',
                    now()->addDays(30),
                    ['comanda' => $comanda->id, 'mockup' => $mockup->id]
                ),
            ];

            $snapshotByType[$type] = [
                'id' => $mockup->id,
                'type' => $type,
                'type_label' => $typeLabel,
                'original_name' => $mockup->original_name,
            ];

            if (count($linksByType) === count($mockupTypes)) {
                break;
            }
        }

        $links = [];
        $snapshot = [];
        foreach ($mockupTypes as $type) {
            if (isset($linksByType[$type])) {
                $links[] = $linksByType[$type];
                $snapshot[] = $snapshotByType[$type];
            }
        }

        return [
            'links' => $links,
            'snapshot' => $snapshot,
        ];
    }

    private function sanitizeMockupLinkTypes(array $mockupTypes): array
    {
        $allowed = array_keys(Mockup::typeOptions());

        return collect($mockupTypes)
            ->map(fn ($value) => (string) $value)
            ->filter(fn (string $value) => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCustomProductNameFromNomenclator(
        string $denumire,
        ?int $selectedNomenclatorId,
        bool $addToNomenclator,
        ?int $userId
    ): array {
        $denumire = trim($denumire);
        if ($denumire === '') {
            return [
                'denumire' => $denumire,
                'canonical_nomenclator_id' => null,
                'added_to_nomenclator' => false,
            ];
        }

        $lookupKey = NomenclatorProdusCustom::makeLookupKey($denumire);
        $canonicalKey = NomenclatorProdusCustom::makeCanonicalKey($denumire);
        if ($lookupKey === '' || $canonicalKey === '') {
            return [
                'denumire' => $denumire,
                'canonical_nomenclator_id' => null,
                'added_to_nomenclator' => false,
            ];
        }

        if ($selectedNomenclatorId) {
            $selected = NomenclatorProdusCustom::find($selectedNomenclatorId);
            $canonicalFromSelected = $selected ? $this->resolveCanonicalCustomProductEntry($selected) : null;
            if ($canonicalFromSelected && $canonicalFromSelected->lookup_key === $lookupKey) {
                return [
                    'denumire' => $canonicalFromSelected->denumire,
                    'canonical_nomenclator_id' => $canonicalFromSelected->id,
                    'added_to_nomenclator' => false,
                ];
            }
        }

        $matchedByLookup = NomenclatorProdusCustom::query()
            ->where('lookup_key', $lookupKey)
            ->first();
        if ($matchedByLookup) {
            $canonical = $this->resolveCanonicalCustomProductEntry($matchedByLookup);
            return [
                'denumire' => $canonical?->denumire ?? $denumire,
                'canonical_nomenclator_id' => $canonical?->id,
                'added_to_nomenclator' => false,
            ];
        }

        $matchedByCanonicalKey = NomenclatorProdusCustom::query()
            ->canonical()
            ->where('canonical_key', $canonicalKey)
            ->orderBy('id')
            ->first();
        if ($matchedByCanonicalKey) {
            $this->ensureCustomProductAlias(
                $matchedByCanonicalKey,
                $denumire,
                $lookupKey,
                $canonicalKey,
                $userId
            );

            return [
                'denumire' => $matchedByCanonicalKey->denumire,
                'canonical_nomenclator_id' => $matchedByCanonicalKey->id,
                'added_to_nomenclator' => false,
            ];
        }

        if ($addToNomenclator) {
            $entry = NomenclatorProdusCustom::query()->firstOrCreate(
                ['lookup_key' => $lookupKey],
                [
                    'denumire' => $denumire,
                    'canonical_key' => $canonicalKey,
                    'canonical_id' => null,
                    'is_canonical' => true,
                    'created_by' => $userId,
                ]
            );

            $canonical = $this->resolveCanonicalCustomProductEntry($entry);
            return [
                'denumire' => $canonical?->denumire ?? $denumire,
                'canonical_nomenclator_id' => $canonical?->id,
                'added_to_nomenclator' => $entry->wasRecentlyCreated,
            ];
        }

        return [
            'denumire' => $denumire,
            'canonical_nomenclator_id' => null,
            'added_to_nomenclator' => false,
        ];
    }

    private function ensureCustomProductAlias(
        NomenclatorProdusCustom $canonical,
        string $denumire,
        string $lookupKey,
        string $canonicalKey,
        ?int $userId
    ): void {
        if ($canonical->lookup_key === $lookupKey) {
            return;
        }

        NomenclatorProdusCustom::query()->firstOrCreate(
            ['lookup_key' => $lookupKey],
            [
                'denumire' => $denumire,
                'canonical_key' => $canonicalKey,
                'canonical_id' => $canonical->id,
                'is_canonical' => false,
                'created_by' => $userId,
            ]
        );
    }

    private function resolveCanonicalCustomProductEntry(?NomenclatorProdusCustom $entry): ?NomenclatorProdusCustom
    {
        if (!$entry) {
            return null;
        }

        if ($entry->is_canonical || !$entry->canonical_id) {
            return $entry;
        }

        return $entry->canonical()->first() ?: $entry;
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

    private function ensureCanOperateFacturaFiles(?User $user): void
    {
        if (!$user || !$user->hasAnyRole(['supervizor'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function ensureCanSendFacturiEmail(?User $user): void
    {
        if (!$user || !$user->hasPermission('facturi.email.send')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function isCerereOferta(Comanda $comanda): bool
    {
        return $comanda->tip === TipComanda::CerereOferta->value;
    }

    private function denyIfCerereOfertaSectionLocked(
        Request $request,
        Comanda $comanda,
        array $scopes,
        string $message
    ) {
        if (!$this->isCerereOferta($comanda)) {
            return null;
        }

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            $message,
            $scopes,
            'warning'
        );
    }

    private function denyIfCerereOfertaNoteRoleLocked(Request $request, Comanda $comanda, string $role)
    {
        if (!$this->isCerereOferta($comanda) || !in_array($role, ['grafician', 'executant'], true)) {
            return null;
        }

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            'Notele de grafician si executant sunt blocate pentru cererile de oferta.',
            ['note'],
            'warning'
        );
    }

    private function canBypassDailyEditLock(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['supervizor', 'superadmin']);
    }

    private function resolveDailyEditLockedAt(?Carbon $createdAt): ?Carbon
    {
        if (!$createdAt) {
            return null;
        }

        $timezone = (string) config('app.timezone', 'UTC');

        return $createdAt
            ->copy()
            ->setTimezone($timezone)
            ->startOfDay()
            ->addDay();
    }

    private function denyIfDailySectionEditLocked(
        Request $request,
        Comanda $comanda,
        ?Carbon $createdAt,
        array $scopes,
        string $baseMessage
    ) {
        if ($this->canBypassDailyEditLock($request->user())) {
            return null;
        }

        $lockedAt = $this->resolveDailyEditLockedAt($createdAt);
        if (!$lockedAt) {
            return null;
        }

        $now = now((string) config('app.timezone', 'UTC'));
        if ($now->lt($lockedAt)) {
            return null;
        }

        $message = trim($baseMessage) . ' Blocare din ' . $lockedAt->format('d.m.Y H:i') . '.';

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            $message,
            $scopes,
            'warning'
        );
    }

    private function respondWithComandaPayload(
        Request $request,
        Comanda $comanda,
        string $message,
        array $scopes = [],
        string $messageType = 'success',
        ?string $sessionKey = null
    )
    {
        if ($request->wantsJson()) {
            return response()->json(
                $this->buildComandaAjaxPayload($request, $comanda, $message, $scopes, $messageType),
                $messageType === 'error' ? 422 : 200
            );
        }

        $sessionKey = $sessionKey
            ?? match ($messageType) {
                'warning' => 'warning',
                'error' => 'error',
                default => 'success',
            };

        return back()->with($sessionKey, $message);
    }

    private function buildComandaAjaxPayload(
        Request $request,
        Comanda $comanda,
        string $message,
        array $scopes = [],
        string $messageType = 'success'
    ): array
    {
        $user = $request->user();
        $scopes = collect($scopes)->filter()->unique()->values()->all();
        $scopeSet = array_fill_keys($scopes, true);

        $payload = [
            'message' => $message,
            'message_type' => $messageType,
            'counts' => [],
        ];

        $canWriteComenzi = $user?->hasPermission('comenzi.write') ?? false;
        $canWriteProduse = $user?->hasPermission('comenzi.produse.write') ?? false;
        $canWriteAtasamente = $user?->hasPermission('comenzi.atasamente.write') ?? false;
        $canWriteMockupuri = $user?->hasPermission('comenzi.mockupuri.write') ?? false;
        $canWritePlati = $user?->hasPermission('comenzi.plati.write') ?? false;
        $canSendOfertaEmail = $user?->hasPermission('comenzi.email.send') ?? false;
        $canBypassDailyEditLock = $this->canBypassDailyEditLock($user);
        $canOperateFacturaFiles = $user?->hasAnyRole(['supervizor']) ?? false;
        $isCerereOferta = $this->isCerereOferta($comanda);

        if (isset($scopeSet['detalii']) || isset($scopeSet['fisiere'])) {
            $statusuri = StatusComanda::options();
            $payload['header_html'] = view('comenzi.partials.header', [
                'comanda' => $comanda,
                'canWriteComenzi' => $canWriteComenzi,
                'statusuri' => $statusuri,
                'canViewFacturi' => $comanda->canViewFacturi($user),
                'canOpenFacturaEmailModal' => !$isCerereOferta && $comanda->canViewFacturi($user),
            ])->render();
        }

        if (isset($scopeSet['necesar']) || isset($scopeSet['plati'])) {
            $comanda->load([
                'produse.produs',
                'plati',
            ]);

            $metodePlata = MetodaPlata::options();
            $statusPlataOptions = StatusPlata::options();

            if (isset($scopeSet['necesar'])) {
                $payload['produse_html'] = view('comenzi.partials.necesar-table-body', [
                    'comanda' => $comanda,
                    'canWriteProduse' => $canWriteProduse,
                ])->render();
                $payload['counts']['necesar'] = $comanda->produse->count();
            }

            $payload['plati_html'] = view('comenzi.partials.plati-table-body', [
                'comanda' => $comanda,
                'metodePlata' => $metodePlata,
                'canWritePlati' => $canWritePlati && !$isCerereOferta,
            ])->render();
            $payload['plati_summary_html'] = view('comenzi.partials.plati-summary', [
                'comanda' => $comanda,
                'statusPlataOptions' => $statusPlataOptions,
            ])->render();
            $payload['counts']['plati'] = $comanda->plati->count();
        }

        if (isset($scopeSet['solicitari'])) {
            $comanda->load(['solicitari.createdBy']);

            $payload['solicitari_html'] = view('comenzi.partials.solicitari-existing', [
                'comanda' => $comanda,
                'canEditNotaFrontdesk' => $comanda->canEditNotaFrontdesk($user) && $canWriteComenzi,
                'canBypassDailyEditLock' => $canBypassDailyEditLock,
            ])->render();
            $payload['counts']['solicitari'] = $comanda->solicitari->count();
        }

        if (isset($scopeSet['note'])) {
            $comanda->load(['note.createdBy']);
            $noteGroups = $comanda->note->groupBy('role');

            $payload['notes_html'] = [
                'frontdesk' => view('comenzi.partials.note-existing-role', [
                    'comanda' => $comanda,
                    'notes' => $noteGroups->get('frontdesk', collect()),
                    'role' => 'frontdesk',
                    'canEditRole' => $comanda->canEditNotaFrontdesk($user) && $canWriteComenzi,
                    'canBypassDailyEditLock' => $canBypassDailyEditLock,
                ])->render(),
                'grafician' => view('comenzi.partials.note-existing-role', [
                    'comanda' => $comanda,
                    'notes' => $noteGroups->get('grafician', collect()),
                    'role' => 'grafician',
                    'canEditRole' => $comanda->canEditNotaGrafician($user) && $canWriteComenzi && !$isCerereOferta,
                    'canBypassDailyEditLock' => $canBypassDailyEditLock,
                ])->render(),
                'executant' => view('comenzi.partials.note-existing-role', [
                    'comanda' => $comanda,
                    'notes' => $noteGroups->get('executant', collect()),
                    'role' => 'executant',
                    'canEditRole' => $comanda->canEditNotaExecutant($user) && $canWriteComenzi && !$isCerereOferta,
                    'canBypassDailyEditLock' => $canBypassDailyEditLock,
                ])->render(),
            ];
            $payload['counts']['note'] = $comanda->note->count();
        }

        if (isset($scopeSet['fisiere'])) {
            $comanda->load([
                'client',
                'atasamente.uploadedBy',
                'facturi' => fn ($query) => $query->latest(),
                'facturi.uploadedBy',
                'facturaEmails' => fn ($query) => $query->latest(),
                'mockupuri' => fn ($query) => $query->latest()->with('uploadedBy'),
            ]);

            $payload['fisiere_html'] = view('comenzi.partials.fisiere-content', [
                'comanda' => $comanda,
                'canWriteAtasamente' => $canWriteAtasamente && !$isCerereOferta,
                'canViewFacturi' => $comanda->canViewFacturi($user),
                'canManageFacturi' => $comanda->canManageFacturi($user) && !$isCerereOferta,
                'canOperateFacturaFiles' => $canOperateFacturaFiles,
                'canWriteMockupuri' => $canWriteMockupuri && !$isCerereOferta,
                'canBypassDailyEditLock' => $canBypassDailyEditLock,
                'mockupTypes' => Mockup::typeOptions(),
                'clientEmail' => optional($comanda->client)->email,
            ])->render();
            $payload['counts']['atasamente'] = $comanda->atasamente->count();
            $payload['counts']['facturi'] = $comanda->facturi->count();
            $payload['counts']['mockupuri'] = $comanda->mockupuri->count();
        }

        if (isset($scopeSet['gdpr'])) {
            $comanda->load([
                'client',
                'gdprConsents' => fn ($query) => $query->latest('signed_at'),
            ]);

            $gdprConsent = $comanda->gdprConsents->first();
            $gdprSignedAt = $gdprConsent?->signed_at ?? $gdprConsent?->created_at;
            $gdprSignedLabel = $gdprSignedAt ? $gdprSignedAt->format('d.m.Y H:i') : null;
            $gdprHasConsent = (bool) $gdprConsent;
            $gdprMethod = $gdprConsent?->method;
            $gdprMarketing = $gdprConsent?->consent_marketing ?? false;
            $gdprMediaMarketing = $gdprConsent?->consent_media_marketing ?? false;
            $clientEmail = optional($comanda->client)->email;
            $canSendGdprEmailEnabled = $canSendOfertaEmail && $gdprHasConsent && !empty($clientEmail);
            $isGdprPhysicalSource = $comanda->sursa === SursaComanda::Fizic->value;

            $payload['gdpr_status_html'] = view('comenzi.partials.gdpr-status', [
                'canWriteComenzi' => $canWriteComenzi,
                'gdprHasConsent' => $gdprHasConsent,
                'comanda' => $comanda,
                'canSendGdprEmailEnabled' => $canSendGdprEmailEnabled,
                'gdprSignedLabel' => $gdprSignedLabel,
                'gdprMethod' => $gdprMethod,
                'gdprMarketing' => $gdprMarketing,
                'gdprMediaMarketing' => $gdprMediaMarketing,
                'isGdprPhysicalSource' => $isGdprPhysicalSource,
                'clientEmail' => $clientEmail,
            ])->render();
            $payload['gdpr'] = [
                'has_consent' => $gdprHasConsent,
                'can_send_email' => $canSendGdprEmailEnabled,
            ];
        }

        return $payload;
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

    private function listComenzi(
        Request $request,
        ?string $fixedTip = null,
        ?string $pageTitle = null,
        bool $onlyTrashed = false
    )
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
        $sort = $request->get('sort', $onlyTrashed ? 'deleted_at' : null);
        $dir = strtolower($request->get('dir', $onlyTrashed ? 'desc' : 'asc'));
        $dir = $dir === 'desc' ? 'desc' : 'asc';
        $currentUserId = auth()->id();

        $query = Comanda::query()
            ->when($onlyTrashed, fn ($builder) => $builder->onlyTrashed())
            ->with([
            'client' => fn ($builder) => $builder->withTrashed(),
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
            'deleted_at' => 'comenzi.deleted_at',
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
            $query->orderBy($onlyTrashed ? 'deleted_at' : 'data_solicitarii', $onlyTrashed ? 'desc' : 'asc');
        }

        $comenzi = $query->paginate(25);

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

        $view = $onlyTrashed ? 'comenzi.trash' : 'comenzi.index';

        return view($view, [
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
            'isTrashView' => $onlyTrashed,
            'trashRoute' => route($this->resolveComenziRouteNameByTip($fixedTip, true)),
            'activeRoute' => route($this->resolveComenziRouteNameByTip($fixedTip, false)),
        ]);
    }

    private function resolveComenziRouteNameByTip(?string $tip, bool $trash = false): string
    {
        if ($tip === TipComanda::CerereOferta->value) {
            return $trash ? 'cereri-oferta.trash' : 'cereri-oferta';
        }

        return $trash ? 'comenzi.trash' : 'comenzi.index';
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

