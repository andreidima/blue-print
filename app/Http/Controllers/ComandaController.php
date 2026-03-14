<?php

namespace App\Http\Controllers;

use App\Enums\MetodaPlata;
use App\Enums\StatusComanda;
use App\Enums\StatusPlata;
use App\Enums\SursaComanda;
use App\Enums\TipComanda;
use App\Exports\Comenzi\ConsumSinteticExport;
use App\Mail\ComandaAssignmentMail;
use App\Mail\ComandaFacturaMail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Client;
use App\Models\Comanda;
use App\Models\ComandaAtasament;
use App\Models\ComandaEtapaHistory;
use App\Models\ComandaEtapaUser;
use App\Models\ComandaFactura;
use App\Models\ComandaFacturaEmail;
use App\Models\ComandaGdprConsent;
use App\Models\ComandaProdus;
use App\Models\ComandaProdusConsum;
use App\Models\ComandaProdusHistory;
use App\Models\ComandaSolicitare;
use App\Models\ComandaNota;
use App\Models\EmailTemplate;
use App\Models\Etapa;
use App\Models\Mockup;
use App\Models\NomenclatorEchipament;
use App\Models\NomenclatorMaterial;
use App\Models\NomenclatorProdusCustom;
use App\Models\Plata;
use App\Models\Produs;
use App\Models\User;
use App\Support\ComandaEmailAttachmentSupport;
use App\Support\ComandaPdfFactory;
use App\Support\EmailContent;
use App\Support\EmailPlaceholders;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'bulkExportConsumSinteticPdf',
            'bulkExportConsumSinteticExcel',
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
            'downloadConsumSinteticPdf',
            'downloadConsumSinteticExcel',
        ]);
        $this->middleware('checkUserPermission:comenzi.produse.write')->only([
            'storeProdus',
            'updateProdus',
            'destroyProdus',
            'storeProdusConsum',
            'updateProdusConsum',
            'destroyProdusConsum',
            'customProductNomenclatorOptions',
        ]);
        $this->middleware('checkUserPermission:comenzi.atasamente.write')->only(['storeAtasament', 'destroyAtasament']);
        $this->middleware('checkUserPermission:comenzi.mockupuri.write')->only(['storeMockup', 'destroyMockup']);
        $this->middleware('checkUserPermission:comenzi.plati.write')->only(['storePlata', 'updatePlata', 'destroyPlata']);
        $this->middleware('checkUserPermission:comenzi.etape.write')->only(['approveAssignments']);
        $this->middleware('checkUserPermission:facturi.write')->only(['storeFactura', 'destroyFactura']);
        $this->middleware('checkUserPermission:facturi.email.send')->only(['trimiteFacturaEmail']);
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
            'afiseaza_detalii' => ['nullable', 'boolean'],
            'solicitari' => ['nullable', 'array'],
            'solicitari.*.solicitare_client' => ['nullable', 'string'],
            'solicitari.*.cantitate' => ['nullable', 'numeric', 'gt:0'],
            'awb' => ['nullable', 'string', 'max:50'],
            'livrator' => ['nullable', 'string', 'max:100'],
        ]);

        $data['necesita_tipar_exemplu'] = $request->boolean('necesita_tipar_exemplu');
        $data['necesita_mockup'] = $request->boolean('necesita_mockup');
        $data['afiseaza_detalii'] = $request->boolean('afiseaza_detalii');
        $data['awb'] = trim((string) ($data['awb'] ?? '')) ?: null;
        $data['livrator'] = trim((string) ($data['livrator'] ?? '')) ?: null;
        if ($this->shouldMarkComandaFinalized($data['tip'], $data['status'])) {
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
            'produse.consumuri.material',
            'produse.consumuri.echipament',
            'produse.consumuri.createdBy.roles',
            'produse.consumuri.updatedBy.roles',
            'atasamente.uploadedBy.roles',
            'facturi' => fn ($query) => $query->latest(),
            'facturi.uploadedBy.roles',
            'facturaEmails' => fn ($query) => $query->latest(),
            'mockupuri' => fn ($query) => $query->latest()->with('uploadedBy.roles'),
            'emailLogs' => fn ($query) => $query->latest(),
            'plati.createdBy.roles',
            'plati.updatedBy.roles',
            'supervizorUser',
            'etapaAssignments',
            'solicitari.createdBy.roles',
            'note.createdBy.roles',
            'gdprConsents' => fn ($query) => $query->latest('signed_at'),
            'produsHistories' => fn ($query) => $query->latest()->limit(50)->with('actor.roles'),
            'etapaHistories' => fn ($query) => $query->latest()->limit(120)->with(['etapa', 'actor.roles', 'targetUser.roles']),
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
        $materiale = NomenclatorMaterial::query()->where('activ', true)->orderBy('denumire')->get();
        $echipamente = NomenclatorEchipament::query()->where('activ', true)->orderBy('denumire')->get();

        $tipuri = TipComanda::options();
        $surse = SursaComanda::options();
        $statusuri = StatusComanda::options();
        $metodePlata = MetodaPlata::options();
        $access = $this->resolveComandaAccess($comanda, $currentUser);

        return view('comenzi.show', compact(
            'comanda',
            'access',
            'activeUsersByRole',
            'etape',
            'assignedUserIdsByEtapa',
            'assignmentStatusesByEtapaUser',
            'produse',
            'materiale',
            'echipamente',
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
        $access = $this->resolveComandaAccess($comanda, $user);
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
            'afiseaza_detalii' => ['nullable', 'boolean'],
            'awb' => ['nullable', 'string', 'max:50'],
            'livrator' => ['nullable', 'string', 'max:100'],
        ];

        $assignmentNotificationSummary = [
            'sent' => 0,
            'failed' => [],
            'skipped' => [],
        ];

        if ($access['canEditAssignments']) {
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
        $data['afiseaza_detalii'] = $request->boolean('afiseaza_detalii');
        $data['awb'] = trim((string) ($data['awb'] ?? '')) ?: null;
        $data['livrator'] = trim((string) ($data['livrator'] ?? '')) ?: null;

        if ($this->shouldMarkComandaFinalized($data['tip'], $data['status'])) {
            $data['finalizat_la'] = $comanda->finalizat_la ?? now();
        } else {
            $data['finalizat_la'] = null;
        }

        $comanda->update($data);

        if ($access['canEditAssignments']) {
            $etapeInput = $request->input('etape', []);
            $etapeConfig = Etapa::query()
                ->get(['id', 'slug', 'label'])
                ->keyBy('id');
            $etapaSlugById = $etapeConfig
                ->map(fn (Etapa $etapa) => (string) $etapa->slug)
                ->all();
            $etapaLabelById = $etapeConfig
                ->map(fn (Etapa $etapa) => trim((string) ($etapa->label ?: ('Etapa #' . $etapa->id))))
                ->all();
            $etapaIds = $etapeConfig->keys()
                ->map(fn ($id) => (int) $id)
                ->all();
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
            $newAssignmentStageLabelsByUserId = [];

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

                $existingAssignments = $comanda->etapaAssignments()
                    ->where('etapa_id', $etapaId)
                    ->get();
                $existingUserIds = $existingAssignments->pluck('user_id')
                    ->map(fn ($value) => (int) $value)
                    ->all();

                $userIdsToDelete = array_diff($existingUserIds, $requestedUserIds);
                if (!empty($userIdsToDelete)) {
                    $assignmentsToDelete = $existingAssignments
                        ->whereIn('user_id', $userIdsToDelete)
                        ->values();

                    foreach ($assignmentsToDelete as $assignmentToDelete) {
                        $this->logEtapaHistory(
                            $comanda,
                            $user?->id,
                            $etapaId,
                            (int) $assignmentToDelete->user_id,
                            'removed',
                            $assignmentToDelete->status,
                            null,
                            null
                        );
                    }

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

                    $this->logEtapaHistory(
                        $comanda,
                        $user?->id,
                        $etapaId,
                        (int) $userId,
                        'assigned',
                        null,
                        ComandaEtapaUser::STATUS_PENDING,
                        null
                    );

                    $newAssignmentStageLabelsByUserId[$userId] ??= [];
                    $newAssignmentStageLabelsByUserId[$userId][] = $etapaLabelById[$etapaId] ?? ('Etapa #' . $etapaId);
                }
            }

            $assignmentNotificationSummary = $this->sendAssignmentNotificationEmails(
                $comanda,
                $newAssignmentStageLabelsByUserId,
                $user
            );
        }

        $message = 'Comanda a fost actualizata cu succes!';
        if (!empty($assignmentNotificationSummary['sent'])) {
            $message .= ' Au fost trimise ' . $assignmentNotificationSummary['sent'] . ' notificari pe email.';
        }
        if (!empty($assignmentNotificationSummary['failed']) || !empty($assignmentNotificationSummary['skipped'])) {
            $message .= ' Unele notificari nu au putut fi trimise.';
        }
        if ($request->wantsJson()) {
            return response()->json(
                $this->buildComandaAjaxPayload($request, $comanda, $message, ['detalii', 'etape'])
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
                'afiseaza_detalii' => (bool) $comanda->afiseaza_detalii,
                'adresa_facturare' => $comanda->adresa_facturare,
                'adresa_livrare' => $comanda->adresa_livrare,
                'awb' => $comanda->awb,
                'livrator' => $comanda->livrator,
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

    public function bulkExportConsumSinteticPdf(Request $request)
    {
        $report = $this->buildConsumSinteticReportFromRequest($request);

        return $this->buildConsumSinteticPdfDownload(
            $report,
            $this->buildConsumSinteticFilename('pdf', null, $report)
        );
    }

    public function bulkExportConsumSinteticExcel(Request $request)
    {
        $report = $this->buildConsumSinteticReportFromRequest($request);

        return $this->buildConsumSinteticExcelDownload(
            $report,
            $this->buildConsumSinteticFilename('xlsx', null, $report)
        );
    }

    public function storeSolicitari(Request $request, Comanda $comanda)
    {
        $user = $request->user();
        $access = $this->resolveComandaAccess($comanda, $user);
        abort_unless($access['canManageSolicitari'], 403);

        $request->validate([
            'solicitari' => ['nullable', 'array'],
            'solicitari.*.solicitare_client' => ['nullable', 'string'],
            'solicitari.*.cantitate' => ['nullable', 'numeric', 'gt:0'],
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
        $access = $this->resolveComandaAccess($comanda, $user);
        abort_unless($access['canManageSolicitari'], 403);
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
        $access = $this->resolveComandaAccess($comanda, $user);
        abort_unless($access['canManageSolicitari'], 403);
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
            'cantitate' => ['nullable', 'numeric', 'gt:0'],
        ]);

        if (array_key_exists('cantitate', $data)) {
            $data['cantitate'] = $data['cantitate'] === null ? null : $this->normalizeQuantity($data['cantitate']);
        }

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
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteProduse'], 403);

        $produsTip = $request->input('produs_tip', 'existing');
        if (!in_array($produsTip, ['existing', 'custom'], true)) {
            $produsTip = 'existing';
        }
        $linieCreata = null;

        if ($produsTip === 'custom') {
            $data = $request->validate([
                'produs_tip' => ['required', Rule::in(['existing', 'custom'])],
                'custom_descriere' => ['nullable', 'string', 'max:1000'],
                'custom_denumire' => ['required', 'string', 'max:255'],
                'custom_nomenclator_id' => ['nullable', 'integer', 'exists:nomenclator_produse_custom,id'],
                'custom_add_to_nomenclator' => ['nullable', 'boolean'],
                'update_custom_description_default' => ['nullable', 'boolean'],
                'custom_pret_unitar' => ['required', 'numeric', 'min:0'],
                'cantitate' => ['required', 'numeric', 'gt:0'],
            ]);

            $pretUnitar = round((float) $data['custom_pret_unitar'], 2);
            $cantitate = $this->normalizeQuantity($data['cantitate']);
            $totalLinie = round($pretUnitar * $cantitate, 2);
            $lineDescription = trim((string) ($data['custom_descriere'] ?? '')) ?: null;
            $resolved = $this->resolveCustomProductNameFromNomenclator(
                trim((string) $data['custom_denumire']),
                isset($data['custom_nomenclator_id']) ? (int) $data['custom_nomenclator_id'] : null,
                $request->boolean('custom_add_to_nomenclator'),
                $request->user()?->id
            );

            $linieCreata = $comanda->produse()->create([
                'produs_id' => null,
                'custom_denumire' => $resolved['denumire'],
                'descriere' => $lineDescription,
                'cantitate' => $cantitate,
                'pret_unitar' => $pretUnitar,
                'total_linie' => $totalLinie,
            ]);

            $canonicalNomenclatorId = $resolved['canonical_nomenclator_id'] ?? null;
            if ($canonicalNomenclatorId) {
                $nomenclatorEntry = NomenclatorProdusCustom::query()->find($canonicalNomenclatorId);
                if ($nomenclatorEntry) {
                    $shouldPersistDefaultDescription = $request->boolean('update_custom_description_default')
                        || ($nomenclatorEntry->descriere === null && $lineDescription !== null);
                    $shouldPersistDefaultPrice = ($resolved['added_to_nomenclator'] ?? false)
                        || $nomenclatorEntry->pret === null;

                    if ($shouldPersistDefaultDescription && $nomenclatorEntry->descriere !== $lineDescription) {
                        $nomenclatorEntry->update(['descriere' => $lineDescription]);
                    }

                    if ($shouldPersistDefaultPrice) {
                        $currentPrice = $nomenclatorEntry->pret !== null
                            ? round((float) $nomenclatorEntry->pret, 2)
                            : null;

                        if ($currentPrice !== $pretUnitar) {
                            $nomenclatorEntry->update(['pret' => $pretUnitar]);
                        }
                    }
                }
            }
        } else {
            $data = $request->validate([
                'produs_tip' => ['nullable', Rule::in(['existing', 'custom'])],
                'produs_id' => ['required', 'exists:produse,id'],
                'descriere' => ['nullable', 'string', 'max:1000'],
                'update_product_description_default' => ['nullable', 'boolean'],
                'cantitate' => ['required', 'numeric', 'gt:0'],
            ]);

            $produs = Produs::findOrFail($data['produs_id']);
            $cantitate = $this->normalizeQuantity($data['cantitate']);
            $totalLinie = round($produs->pret * $cantitate, 2);
            $lineDescription = trim((string) ($data['descriere'] ?? '')) ?: null;

            $linieCreata = $comanda->produse()->create([
                'produs_id' => $produs->id,
                'descriere' => $lineDescription,
                'cantitate' => $cantitate,
                'pret_unitar' => $produs->pret,
                'total_linie' => $totalLinie,
            ]);

            $shouldPersistDefaultDescription = $request->boolean('update_product_description_default')
                || ($produs->descriere === null && $lineDescription !== null);
            if ($shouldPersistDefaultDescription && $produs->descriere !== $lineDescription) {
                $produs->update(['descriere' => $lineDescription]);
            }
        }

        if ($linieCreata) {
            $this->logProdusHistory(
                $comanda,
                $request->user()?->id,
                'created',
                $linieCreata,
                null,
                $this->formatProdusState($linieCreata)
            );
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
            ['necesar', 'consum', 'plati']
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
                    'pret' => $canonical->pret !== null ? round((float) $canonical->pret, 2) : null,
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
                'pret' => $entry->pret !== null ? round((float) $entry->pret, 2) : null,
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
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteAtasamente'], 403);

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
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteMockupuri'], 403);

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
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteAtasamente'], 403);

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

        if ($this->facturaWasSentByEmail($comanda, $factura)) {
            return $this->respondWithComandaPayload(
                $request,
                $comanda,
                'Factura nu poate fi stearsa deoarece a fost deja trimisa prin email.',
                ['fisiere']
            );
        }

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

        if ($this->mockupWasSentByEmail($comanda, $mockup)) {
            return $this->respondWithComandaPayload(
                $request,
                $comanda,
                'Fisierul info nu poate fi sters deoarece a fost deja trimis prin email.',
                ['fisiere']
            );
        }

        if ($response = $this->denyIfCerereOfertaSectionLocked(
            $request,
            $comanda,
            ['fisiere'],
            'Sectiunea fisiere este blocata pentru cererile de oferta.'
        )) {
            return $response;
        }
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteMockupuri'], 403);

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
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWritePlatiCreate'], 403);

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
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteProduse'], 403);
        abort_unless($linie->comanda_id === $comanda->id, 404);

        $before = $this->formatProdusState($linie);

        $data = $request->validate([
            'descriere' => ['nullable', 'string', 'max:1000'],
            'cantitate' => ['required', 'numeric', 'gt:0'],
            'pret_unitar' => ['required', 'numeric', 'min:0'],
        ]);

        $cantitate = $this->normalizeQuantity($data['cantitate']);
        $pretUnitar = round((float) $data['pret_unitar'], 2);

        $linie->update([
            'descriere' => trim((string) ($data['descriere'] ?? '')) ?: null,
            'cantitate' => $cantitate,
            'pret_unitar' => $pretUnitar,
            'total_linie' => round($cantitate * $pretUnitar, 2),
        ]);

        $this->logProdusHistory(
            $comanda,
            $request->user()?->id,
            'updated',
            $linie,
            $before,
            $this->formatProdusState($linie)
        );

        $comanda->recalculateTotals();

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            'Linia de produs a fost actualizata.',
            ['necesar', 'consum', 'plati']
        );
    }

    public function destroyProdus(Request $request, Comanda $comanda, ComandaProdus $linie)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteProduse'], 403);
        abort_unless($linie->comanda_id === $comanda->id, 404);

        $before = $this->formatProdusState($linie);
        $this->logProdusHistory(
            $comanda,
            $request->user()?->id,
            'deleted',
            $linie,
            $before,
            null
        );

        $linie->delete();
        $comanda->recalculateTotals();

        $message = 'Produsul a fost eliminat.';

        return $this->respondWithComandaPayload($request, $comanda, $message, ['necesar', 'consum', 'plati']);
    }

    public function storeProdusConsum(Request $request, Comanda $comanda, ComandaProdus $linie)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteConsum'], 403);
        abort_unless($linie->comanda_id === $comanda->id, 404);

        $data = $request->validate([
            'material_id' => ['nullable', 'integer', 'exists:nomenclator_materiale,id'],
            'material_denumire' => ['required', 'string', 'max:150'],
            'material_add_to_nomenclator' => ['nullable', 'boolean'],
            'unitate_masura' => ['required', 'string', 'max:30'],
            'cantitate_totala' => ['required', 'numeric', 'min:0.0001'],
            'cantitate_rebutata' => ['nullable', 'numeric', 'min:0'],
            'echipament_id' => ['nullable', 'integer', 'exists:nomenclator_echipamente,id'],
            'echipament_denumire' => ['nullable', 'string', 'max:150'],
            'echipament_add_to_nomenclator' => ['nullable', 'boolean'],
            'observatii' => ['nullable', 'string', 'max:2000'],
        ]);

        $resolvedMaterial = $this->resolveMaterialFromNomenclator(
            (string) $data['material_denumire'],
            isset($data['material_id']) ? (int) $data['material_id'] : null,
            $request->boolean('material_add_to_nomenclator'),
            trim((string) $data['unitate_masura']),
            $request->user()?->id
        );
        $resolvedEquipment = $this->resolveEquipmentFromNomenclator(
            (string) ($data['echipament_denumire'] ?? ''),
            isset($data['echipament_id']) ? (int) $data['echipament_id'] : null,
            $request->boolean('echipament_add_to_nomenclator'),
            $request->user()?->id
        );
        $cantitateTotala = round((float) $data['cantitate_totala'], 4);
        $cantitateRebutata = round((float) ($data['cantitate_rebutata'] ?? 0), 4);

        $linie->consumuri()->create([
            'material_id' => $resolvedMaterial['id'],
            'material_denumire' => $resolvedMaterial['denumire'],
            'unitate_masura' => $resolvedMaterial['unitate_masura'],
            'cantitate_totala' => $cantitateTotala,
            'cantitate_rebutata' => $cantitateRebutata,
            'echipament_id' => $resolvedEquipment['id'],
            'echipament_denumire' => $resolvedEquipment['denumire'],
            'observatii' => trim((string) ($data['observatii'] ?? '')) ?: null,
            'created_by' => $request->user()?->id,
        ]);

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            'Consumul de materiale a fost adaugat.',
            ['consum']
        );
    }

    public function updateProdusConsum(Request $request, Comanda $comanda, ComandaProdus $linie, ComandaProdusConsum $consum)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteConsum'], 403);
        abort_unless($linie->comanda_id === $comanda->id, 404);
        abort_unless($consum->comanda_produs_id === $linie->id, 404);

        $data = $request->validate([
            'material_id' => ['nullable', 'integer', 'exists:nomenclator_materiale,id'],
            'material_denumire' => ['required', 'string', 'max:150'],
            'material_add_to_nomenclator' => ['nullable', 'boolean'],
            'unitate_masura' => ['required', 'string', 'max:30'],
            'cantitate_totala' => ['required', 'numeric', 'min:0.0001'],
            'cantitate_rebutata' => ['nullable', 'numeric', 'min:0'],
            'echipament_id' => ['nullable', 'integer', 'exists:nomenclator_echipamente,id'],
            'echipament_denumire' => ['nullable', 'string', 'max:150'],
            'echipament_add_to_nomenclator' => ['nullable', 'boolean'],
            'observatii' => ['nullable', 'string', 'max:2000'],
        ]);

        $resolvedMaterial = $this->resolveMaterialFromNomenclator(
            (string) $data['material_denumire'],
            isset($data['material_id']) ? (int) $data['material_id'] : null,
            $request->boolean('material_add_to_nomenclator'),
            trim((string) $data['unitate_masura']),
            $request->user()?->id
        );
        $resolvedEquipment = $this->resolveEquipmentFromNomenclator(
            (string) ($data['echipament_denumire'] ?? ''),
            isset($data['echipament_id']) ? (int) $data['echipament_id'] : null,
            $request->boolean('echipament_add_to_nomenclator'),
            $request->user()?->id
        );
        $cantitateTotala = round((float) $data['cantitate_totala'], 4);
        $cantitateRebutata = round((float) ($data['cantitate_rebutata'] ?? 0), 4);

        $consum->update([
            'material_id' => $resolvedMaterial['id'],
            'material_denumire' => $resolvedMaterial['denumire'],
            'unitate_masura' => $resolvedMaterial['unitate_masura'],
            'cantitate_totala' => $cantitateTotala,
            'cantitate_rebutata' => $cantitateRebutata,
            'echipament_id' => $resolvedEquipment['id'],
            'echipament_denumire' => $resolvedEquipment['denumire'],
            'observatii' => trim((string) ($data['observatii'] ?? '')) ?: null,
            'updated_by' => $request->user()?->id,
        ]);

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            'Consumul de materiale a fost actualizat.',
            ['consum']
        );
    }

    public function destroyProdusConsum(Request $request, Comanda $comanda, ComandaProdus $linie, ComandaProdusConsum $consum)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteConsum'], 403);
        abort_unless($linie->comanda_id === $comanda->id, 404);
        abort_unless($consum->comanda_produs_id === $linie->id, 404);

        $consum->delete();

        return $this->respondWithComandaPayload(
            $request,
            $comanda,
            'Randul de consum a fost eliminat.',
            ['consum']
        );
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
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWritePlatiEditExisting'], 403);

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
            'updated_by' => auth()->id(),
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
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWritePlatiEditExisting'], 403);

        $plata->delete();
        $comanda->recalculateTotals();

        $message = 'Plata a fost eliminata.';

        return $this->respondWithComandaPayload($request, $comanda, $message, ['plati']);
    }

    public function approveAssignments(Request $request, Comanda $comanda)
    {
        $user = $request->user();
        $access = $this->resolveComandaAccess($comanda, $user);
        abort_unless($access['canEditAssignments'], 403);

        $userId = $user?->id;
        if (!$userId) {
            return back()->with('warning', 'Trebuie sa fii autentificat pentru a aproba cererea.');
        }

        $query = ComandaEtapaUser::query()
            ->where('comanda_id', $comanda->id)
            ->where('user_id', $userId)
            ->where('status', ComandaEtapaUser::STATUS_PENDING);
        if ($this->isCerereOferta($comanda)) {
            $preluareEtapaId = Etapa::where('slug', 'preluare_comanda')->value('id');
            if (!$preluareEtapaId) {
                return back()->with('warning', 'Etapa preluare comanda nu este configurata.');
            }

            $query->where('etapa_id', $preluareEtapaId);
        }

        $pendingAssignments = $query->get();
        $updated = 0;
        foreach ($pendingAssignments as $assignment) {
            $previousStatus = $assignment->status;
            $assignment->update(['status' => ComandaEtapaUser::STATUS_APPROVED]);
            $updated++;

            $this->logEtapaHistory(
                $comanda,
                $userId,
                (int) $assignment->etapa_id,
                (int) $assignment->user_id,
                'approved',
                $previousStatus,
                ComandaEtapaUser::STATUS_APPROVED,
                null
            );
        }

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

        [$attachments, $snapshotWarnings] = $this->buildFacturaEmailSnapshots(
            $comanda,
            $facturi,
            $mockupLinks['snapshot']
        );

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
                'attachments' => $attachments,
                'document' => 'factura',
                'info_links' => $mockupLinks['snapshot'],
            ],
        ]);

        $message = 'Emailul cu factura a fost trimis.';
        if ($snapshotWarnings !== []) {
            $message .= ' Unele snapshot-uri nu au putut fi salvate: ' . implode('; ', $snapshotWarnings) . '.';
        }

        return back()->with('success', $message);
    }

    public function downloadOfertaPdf(Request $request, Comanda $comanda)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canDownloadOfertaPdf'], 403, 'Nu ai acces la oferta cu preturi.');

        return $this->buildOfertaPdfDownload($comanda);
    }

    public function previewOfertaPdf(Request $request, Comanda $comanda)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canDownloadOfertaPdf'], 403, 'Nu ai acces la oferta cu preturi.');
        abort_unless($access['canPreviewPdf'], 403, 'Nu ai acces la previzualizarea PDF.');

        return $this->buildOfertaPdfStream($comanda);
    }

    public function downloadOfertaPdfSigned(Comanda $comanda)
    {
        return $this->buildOfertaPdfDownload($comanda);
    }

    public function downloadFisaInternaPdf(Request $request, Comanda $comanda)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canDownloadInternalDocs'], 403, 'Nu ai acces la acest document.');

        if ($this->isCerereOferta($comanda)) {
            abort(403, 'Fisa interna nu este disponibila pentru cererile de oferta.');
        }

        return $this->buildFisaInternaPdfDownload($comanda);
    }

    public function previewFisaInternaPdf(Request $request, Comanda $comanda)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canDownloadInternalDocs'], 403, 'Nu ai acces la acest document.');
        abort_unless($access['canPreviewPdf'], 403, 'Nu ai acces la previzualizarea PDF.');

        if ($this->isCerereOferta($comanda)) {
            abort(403, 'Fisa interna nu este disponibila pentru cererile de oferta.');
        }

        return $this->buildFisaInternaPdfStream($comanda);
    }

    public function downloadProcesVerbalPdf(Request $request, Comanda $comanda)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canDownloadInternalDocs'], 403, 'Nu ai acces la acest document.');

        if ($this->isCerereOferta($comanda)) {
            abort(403, 'Procesul verbal nu este disponibil pentru cererile de oferta.');
        }

        return $this->buildProcesVerbalPdfDownload($comanda);
    }

    public function previewProcesVerbalPdf(Request $request, Comanda $comanda)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canDownloadInternalDocs'], 403, 'Nu ai acces la acest document.');
        abort_unless($access['canPreviewPdf'], 403, 'Nu ai acces la previzualizarea PDF.');

        if ($this->isCerereOferta($comanda)) {
            abort(403, 'Procesul verbal nu este disponibil pentru cererile de oferta.');
        }

        return $this->buildProcesVerbalPdfStream($comanda);
    }

    public function downloadConsumSinteticPdf(Request $request, Comanda $comanda)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteComenzi'], 403, 'Nu ai acces la acest document.');

        $report = $this->buildSingleConsumSinteticReport(
            $this->loadConsumSinteticComenzi(collect([$comanda->id])),
            $comanda,
            $request->user()
        );

        return $this->buildConsumSinteticPdfDownload(
            $report,
            $this->buildConsumSinteticFilename('pdf', $comanda, $report)
        );
    }

    public function downloadConsumSinteticExcel(Request $request, Comanda $comanda)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canWriteComenzi'], 403, 'Nu ai acces la acest document.');

        $report = $this->buildSingleConsumSinteticReport(
            $this->loadConsumSinteticComenzi(collect([$comanda->id])),
            $comanda,
            $request->user()
        );

        return $this->buildConsumSinteticExcelDownload(
            $report,
            $this->buildConsumSinteticFilename('xlsx', $comanda, $report)
        );
    }

    public function storeGdprConsent(Request $request, Comanda $comanda)
    {
        $requiresSignature = $comanda->sursa === SursaComanda::Fizic->value;

        $data = $request->validate([
            'method' => ['nullable', Rule::in(['signature', 'checkbox'])],
            'consent_processing' => ['accepted'],
            'consent_marketing' => ['nullable', 'boolean'],
            'consent_media_marketing' => ['nullable', 'boolean'],
            'consent_research_statistics' => ['nullable', 'boolean'],
            'consent_online_communications' => ['nullable', 'boolean'],
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
        $consentResearchStatistics = $requiresSignature
            ? $request->boolean('consent_research_statistics')
            : true;
        $consentOnlineCommunications = $requiresSignature
            ? $request->boolean('consent_online_communications')
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
            'consent_research_statistics' => $consentResearchStatistics,
            'consent_online_communications' => $consentOnlineCommunications,
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

    public function downloadGdprPdf(Request $request, Comanda $comanda)
    {
        $consent = $this->resolveLatestGdprConsent($comanda);
        if (! $consent) {
            return back()->with('warning', 'Nu exista un acord GDPR inregistrat.');
        }

        return $this->buildGdprPdfDownload($comanda, $consent);
    }

    public function previewGdprPdf(Request $request, Comanda $comanda)
    {
        $access = $this->resolveComandaAccess($comanda, $request->user());
        abort_unless($access['canPreviewPdf'], 403, 'Nu ai acces la previzualizarea PDF.');

        $consent = $this->resolveLatestGdprConsent($comanda);
        if (! $consent) {
            return back()->with('warning', 'Nu exista un acord GDPR inregistrat.');
        }

        return $this->buildGdprPdfStream($comanda, $consent);
    }

    public function downloadGdprPdfSigned(Comanda $comanda)
    {
        $consent = $this->resolveLatestGdprConsent($comanda);
        if (! $consent) {
            abort(404);
        }

        return $this->buildGdprPdfDownload($comanda, $consent);
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
                'path' => $mockup->path,
                'mime' => $mockup->mime,
                'size' => $mockup->size,
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

    private function buildFacturaEmailSnapshots(Comanda $comanda, Collection $facturi, array $mockupSnapshots): array
    {
        $attachments = [];
        $warnings = [];

        foreach ($facturi as $factura) {
            $snapshot = ComandaEmailAttachmentSupport::storePublicFileSnapshot(
                $comanda,
                ComandaEmailAttachmentSupport::ENTRY_FACTURA_EMAIL,
                ComandaEmailAttachmentSupport::KIND_FACTURA,
                $factura->original_name ?: ('factura-' . $factura->id . '.pdf'),
                (string) $factura->path,
                $factura->mime,
                $factura->size,
                [
                    'label' => 'Factura',
                    'source_id' => $factura->id,
                ]
            );

            if ($snapshot) {
                $attachments[] = $snapshot;
            } else {
                $warnings[] = 'Factura ' . ($factura->original_name ?: ('#' . $factura->id));
            }
        }

        foreach ($mockupSnapshots as $mockupSnapshot) {
            if (!is_array($mockupSnapshot) || empty($mockupSnapshot['path'])) {
                continue;
            }

            $typeLabel = trim((string) ($mockupSnapshot['type_label'] ?? 'Info'));
            $snapshot = ComandaEmailAttachmentSupport::storePublicFileSnapshot(
                $comanda,
                ComandaEmailAttachmentSupport::ENTRY_FACTURA_EMAIL,
                ComandaEmailAttachmentSupport::KIND_MOCKUP,
                (string) ($mockupSnapshot['original_name'] ?? ($typeLabel !== '' ? $typeLabel : 'info')),
                (string) $mockupSnapshot['path'],
                $mockupSnapshot['mime'] ?? null,
                isset($mockupSnapshot['size']) ? (int) $mockupSnapshot['size'] : null,
                [
                    'label' => $typeLabel !== '' ? $typeLabel : 'Info',
                    'source_id' => isset($mockupSnapshot['id']) ? (int) $mockupSnapshot['id'] : null,
                    'type_label' => $typeLabel !== '' ? $typeLabel : null,
                ]
            );

            if ($snapshot) {
                $attachments[] = $snapshot;
            } else {
                $warnings[] = 'Info ' . ((string) ($mockupSnapshot['original_name'] ?? ($typeLabel !== '' ? $typeLabel : 'mockup')));
            }
        }

        return [$attachments, $warnings];
    }

    private function facturaWasSentByEmail(Comanda $comanda, ComandaFactura $factura): bool
    {
        $comanda->loadMissing([
            'facturaEmails',
            'emailLogs',
        ]);

        return in_array(
            $factura->id,
            ComandaEmailAttachmentSupport::collectSentSourceIds($comanda, ComandaEmailAttachmentSupport::KIND_FACTURA),
            true
        );
    }

    private function mockupWasSentByEmail(Comanda $comanda, Mockup $mockup): bool
    {
        $comanda->loadMissing([
            'facturaEmails',
            'emailLogs',
        ]);

        return in_array(
            $mockup->id,
            ComandaEmailAttachmentSupport::collectSentSourceIds($comanda, ComandaEmailAttachmentSupport::KIND_MOCKUP),
            true
        );
    }

    private function createOfertaPdf(Comanda $comanda)
    {
        return ComandaPdfFactory::oferta($comanda);
    }

    private function buildOfertaPdfDownload(Comanda $comanda)
    {
        return $this->createOfertaPdf($comanda)
            ->download("oferta-comerciala-{$comanda->id}.pdf");
    }

    private function buildOfertaPdfStream(Comanda $comanda)
    {
        return $this->createOfertaPdf($comanda)
            ->stream("oferta-comerciala-{$comanda->id}.pdf");
    }

    private function createFisaInternaPdf(Comanda $comanda)
    {
        $comanda->load([
            'client',
            'produse.produs',
            'note.createdBy.roles',
            'atasamente',
            'facturi',
            'mockupuri',
            'plati',
            'etapaAssignments.etapa',
            'etapaAssignments.user.roles',
            'solicitari.createdBy',
        ]);

        return Pdf::loadView('pdf.comenzi.fisa-interna', [
            'comanda' => $comanda,
        ]);
    }

    private function buildFisaInternaPdfDownload(Comanda $comanda)
    {
        return $this->createFisaInternaPdf($comanda)
            ->download("fisa-interna-comanda-{$comanda->id}.pdf");
    }

    private function buildFisaInternaPdfStream(Comanda $comanda)
    {
        return $this->createFisaInternaPdf($comanda)
            ->stream("fisa-interna-comanda-{$comanda->id}.pdf");
    }

    private function createProcesVerbalPdf(Comanda $comanda)
    {
        $comanda->load(['client', 'produse.produs']);

        return Pdf::loadView('pdf.comenzi.proces-verbal', [
            'comanda' => $comanda,
        ]);
    }

    private function buildProcesVerbalPdfDownload(Comanda $comanda)
    {
        return $this->createProcesVerbalPdf($comanda)
            ->download("proces-verbal-predare-comanda-{$comanda->id}.pdf");
    }

    private function buildProcesVerbalPdfStream(Comanda $comanda)
    {
        return $this->createProcesVerbalPdf($comanda)
            ->stream("proces-verbal-predare-comanda-{$comanda->id}.pdf");
    }

    private function createGdprPdf(Comanda $comanda, ComandaGdprConsent $consent)
    {
        return ComandaPdfFactory::gdpr($comanda, $consent);
    }

    private function buildGdprPdfDownload(Comanda $comanda, ComandaGdprConsent $consent)
    {
        return $this->createGdprPdf($comanda, $consent)
            ->download("gdpr-comanda-{$comanda->id}.pdf");
    }

    private function buildGdprPdfStream(Comanda $comanda, ComandaGdprConsent $consent)
    {
        return $this->createGdprPdf($comanda, $consent)
            ->stream("gdpr-comanda-{$comanda->id}.pdf");
    }

    private function buildConsumSinteticReportFromRequest(Request $request): array
    {
        $data = $request->validate([
            'comanda_ids' => ['required', 'array', 'min:1'],
            'comanda_ids.*' => ['required', 'integer', 'distinct', 'exists:comenzi,id'],
        ]);

        $ids = collect($data['comanda_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $comenzi = $this->loadConsumSinteticComenzi($ids);

        if ($comenzi->isEmpty()) {
            throw ValidationException::withMessages([
                'comanda_ids' => 'Selecteaza cel putin o comanda pentru export.',
            ]);
        }

        return $this->buildMultipleConsumSinteticReport($comenzi, $request->user());
    }

    private function loadConsumSinteticComenzi(Collection $ids): Collection
    {
        return Comanda::query()
            ->with([
                'client',
                'produse.produs',
                'produse.consumuri.material',
                'produse.consumuri.echipament',
                'produse.consumuri.createdBy',
            ])
            ->whereIn('id', $ids->all())
            ->orderBy('data_solicitarii')
            ->orderBy('id')
            ->get();
    }

    private function buildSingleConsumSinteticReport(Collection $comenzi, Comanda $selectedComanda, ?User $user): array
    {
        $comenzi = $comenzi->values();
        $comanda = $comenzi->firstWhere('id', $selectedComanda->id) ?? $selectedComanda;

        return [
            'mode' => 'single',
            'title' => 'FISA SINTETICA COMANDA-CONSUMURI',
            'generated_at' => now(),
            'generated_by' => $user?->name ?? '-',
            'order_count' => 1,
            'detail_rows' => $this->buildSingleConsumSinteticDetailRows($comanda),
            'summary_rows' => $this->buildConsumSinteticSummaryRows($comenzi),
            'order_meta' => [
                'order_id' => $comanda->id,
                'order_date' => $this->formatConsumSinteticDate($comanda->data_solicitarii),
                'client_name' => trim((string) (optional($comanda->client)->nume_complet ?? '-')) ?: '-',
                'completed_at' => $this->formatConsumSinteticDateTime($comanda->finalizat_la),
                'delivery_at' => $this->formatConsumSinteticDateTime($comanda->timp_estimat_livrare),
            ],
        ];
    }

    private function buildSingleConsumSinteticDetailRows(Comanda $comanda): Collection
    {
        return $comanda->produse
            ->flatMap(function ($linie) {
                $productLabel = trim((string) ($linie->custom_denumire ?: optional($linie->produs)->denumire ?: '-'));
                $productQuantity = (float) $linie->cantitate;

                return $linie->consumuri->map(function ($consum) use ($productLabel, $productQuantity) {
                    return [
                        'product_group_key' => $consum->comanda_produs_id,
                        'product' => $productLabel !== '' ? $productLabel : '-',
                        'product_quantity' => $productQuantity,
                        'material' => $consum->materialLabel(),
                        'unitate_masura' => (string) $consum->unitate_masura,
                        'consum' => (float) $consum->cantitate_totala,
                        'equipment' => $consum->echipamentLabel(),
                        'recorded_at' => optional($consum->created_at)->format('d.m.Y H:i') ?? '-',
                        'recorded_by' => optional($consum->createdBy)->name ?? '-',
                        'rebut' => (float) $consum->cantitate_rebutata,
                        'total' => (float) $consum->totalConsumCuRebut(),
                    ];
                });
            })
            ->values();
    }

    private function buildMultipleConsumSinteticReport(Collection $comenzi, ?User $user): array
    {
        $comenzi = $comenzi->values();
        $periodDates = $comenzi
            ->map(fn (Comanda $comanda) => $this->resolveConsumSinteticReferenceDate($comanda))
            ->filter();

        $periodStart = $periodDates->min();
        $periodEnd = $periodDates->max();

        $clientIds = $comenzi
            ->pluck('client_id')
            ->filter()
            ->unique()
            ->values();

        $firstOrderDatesByClient = collect();
        if ($clientIds->isNotEmpty()) {
            $firstOrderDatesByClient = Comanda::query()
                ->selectRaw('client_id, MIN(data_solicitarii) as first_order_date')
                ->whereIn('client_id', $clientIds->all())
                ->whereNotNull('client_id')
                ->groupBy('client_id')
                ->pluck('first_order_date', 'client_id');
        }

        $newClientsCount = $clientIds->filter(function ($clientId) use ($firstOrderDatesByClient, $periodStart, $periodEnd) {
            if (!$periodStart || !$periodEnd) {
                return false;
            }

            $firstOrderDate = $firstOrderDatesByClient->get($clientId);
            if (!$firstOrderDate) {
                return false;
            }

            $parsed = Carbon::parse((string) $firstOrderDate)->startOfDay();

            return $parsed->between($periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay(), true);
        })->count();

        $periodLabel = '-';
        if ($periodStart && $periodEnd) {
            $periodLabel = $periodStart->format('d.m.Y') . ' - ' . $periodEnd->format('d.m.Y');
        }

        return [
            'mode' => 'multiple',
            'title' => 'FISA SINTETICA COMENZI-CONSUMURI',
            'period_label' => $periodLabel,
            'generated_at' => now(),
            'generated_by' => $user?->name ?? '-',
            'order_count' => $comenzi->count(),
            'client_count' => $clientIds->count(),
            'new_client_count' => $newClientsCount,
            'detail_rows' => $this->buildMultipleConsumSinteticDetailRows($comenzi),
            'summary_rows' => $this->buildConsumSinteticSummaryRows($comenzi),
        ];
    }

    private function buildMultipleConsumSinteticDetailRows(Collection $comenzi): Collection
    {
        return $comenzi
            ->flatMap(function (Comanda $comanda) {
                return $comanda->produse->flatMap(function ($linie) use ($comanda) {
                    $productLabel = trim((string) ($linie->custom_denumire ?: optional($linie->produs)->denumire ?: '-'));
                    $productQuantity = (float) $linie->cantitate;
                    $displayLabel = 'Comanda #' . $comanda->id . ' - ' . ($productLabel !== '' ? $productLabel : '-');

                    return $linie->consumuri->map(function ($consum) use ($displayLabel, $productQuantity, $comanda, $linie) {
                        return [
                            'product_group_key' => $comanda->id . ':' . $linie->id,
                            'product' => $displayLabel,
                            'product_quantity' => $productQuantity,
                            'material' => $consum->materialLabel(),
                            'unitate_masura' => (string) $consum->unitate_masura,
                            'consum' => (float) $consum->cantitate_totala,
                            'equipment' => $consum->echipamentLabel(),
                            'recorded_at' => optional($consum->created_at)->format('d.m.Y H:i') ?? '-',
                            'recorded_by' => optional($consum->createdBy)->name ?? '-',
                            'rebut' => (float) $consum->cantitate_rebutata,
                            'total' => (float) $consum->totalConsumCuRebut(),
                        ];
                    });
                });
            })
            ->values();
    }

    private function buildConsumSinteticSummaryRows(Collection $comenzi): Collection
    {
        $consumRows = $comenzi->flatMap(fn (Comanda $comanda) => $comanda->produse->flatMap(fn ($linie) => $linie->consumuri));

        return $consumRows
            ->groupBy(function ($consum) {
                return mb_strtolower($consum->materialLabel()) . '||' . mb_strtolower((string) $consum->unitate_masura);
            })
            ->map(function (Collection $group) {
                $first = $group->first();
                $equipmentLabels = $group
                    ->map(fn ($item) => $item->echipamentLabel())
                    ->filter(fn ($label) => $label !== '-')
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'material' => $first?->materialLabel() ?? '-',
                    'unitate_masura' => $first?->unitate_masura ?? '',
                    'echipamente' => $equipmentLabels === [] ? '-' : implode(', ', $equipmentLabels),
                    'consum' => (float) $group->sum(fn ($item) => (float) $item->cantitate_totala),
                    'rebut' => (float) $group->sum(fn ($item) => (float) $item->cantitate_rebutata),
                    'total' => (float) $group->sum(fn ($item) => (float) $item->totalConsumCuRebut()),
                ];
            })
            ->sortBy(fn (array $row) => mb_strtolower($row['material']))
            ->values();
    }

    private function resolveConsumSinteticReferenceDate(Comanda $comanda): ?Carbon
    {
        if ($comanda->data_solicitarii instanceof Carbon) {
            return $comanda->data_solicitarii->copy();
        }

        if ($comanda->data_solicitarii) {
            return Carbon::parse((string) $comanda->data_solicitarii);
        }

        return $comanda->created_at?->copy();
    }

    private function createConsumSinteticPdf(array $report)
    {
        return Pdf::loadView($this->resolveConsumSinteticPdfView($report), [
            'report' => $report,
        ])->setPaper('a4', 'landscape');
    }

    private function buildConsumSinteticPdfDownload(array $report, string $filename)
    {
        return $this->createConsumSinteticPdf($report)->download($filename);
    }

    private function buildConsumSinteticExcelDownload(array $report, string $filename)
    {
        if (($report['mode'] ?? null) === 'single') {
            return $this->buildSingleConsumSinteticTemplateExcelDownload($report, $filename);
        }

        if (($report['mode'] ?? null) === 'multiple') {
            return $this->buildMultipleConsumSinteticTemplateExcelDownload($report, $filename);
        }

        return Excel::download(
            new ConsumSinteticExport($report, $this->resolveConsumSinteticExcelView($report)),
            $filename
        );
    }

    private function buildConsumSinteticFilename(string $extension, ?Comanda $comanda, array $report): string
    {
        $periodSource = $report['mode'] === 'single'
            ? ($report['order_meta']['order_date'] ?? '-')
            : ($report['period_label'] ?? '-');
        $periodPart = preg_replace('/[^A-Za-z0-9-]+/', '-', str_replace('.', '-', (string) $periodSource));
        $periodPart = trim((string) $periodPart, '-');
        $base = $report['mode'] === 'single' && $comanda
            ? "fisa-sintetica-comanda-{$comanda->id}-consumuri"
            : 'fisa-sintetica-comenzi-consumuri';

        return "{$base}-" . ($periodPart !== '' ? $periodPart : 'export') . ".{$extension}";
    }

    private function resolveConsumSinteticPdfView(array $report): string
    {
        return $report['mode'] === 'single'
            ? 'pdf.comenzi.consum-sintetic-single'
            : 'pdf.comenzi.consum-sintetic-multiple';
    }

    private function resolveConsumSinteticExcelView(array $report): string
    {
        return $report['mode'] === 'single'
            ? 'exports.comenzi.consum-sintetic-single'
            : 'exports.comenzi.consum-sintetic-multiple';
    }

    private function buildSingleConsumSinteticTemplateExcelDownload(array $report, string $filename)
    {
        $spreadsheet = $this->buildSingleConsumSinteticTemplateSpreadsheet($report);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildMultipleConsumSinteticTemplateExcelDownload(array $report, string $filename)
    {
        $spreadsheet = $this->buildMultipleConsumSinteticTemplateSpreadsheet($report);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildSingleConsumSinteticTemplateSpreadsheet(array $report): Spreadsheet
    {
        $templatePath = resource_path('excel-templates/consum-sintetic-comanda-template.xlsx');
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Lipseste template-ul pentru exportul unei comenzi.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getSheet(0);

        $orderMeta = $report['order_meta'] ?? [];
        $detailRows = collect($report['detail_rows'] ?? []);
        $summaryRows = collect($report['summary_rows'] ?? []);

        $sheet->setCellValue('C2', (string) ($orderMeta['order_id'] ?? '-'));
        $sheet->setCellValue('C3', (string) ($orderMeta['order_date'] ?? '-'));
        $sheet->setCellValue('C4', (string) ($orderMeta['client_name'] ?? '-'));
        $sheet->setCellValue('C5', (string) ($orderMeta['completed_at'] ?? '-'));
        $sheet->setCellValue('C6', (string) ($orderMeta['delivery_at'] ?? '-'));
        $sheet->setCellValue('C7', (string) ($report['generated_by'] ?? '-'));
        $sheet->setCellValue('C8', (string) (optional($report['generated_at'] ?? null)->format('d.m.Y H:i') ?? '-'));
        $sheet->getStyle('C2:C8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('C2')->getFont()->setBold(true);
        $sheet->unmergeCells('C10:F10');
        $sheet->mergeCells('C10:E10');
        $sheet->setCellValue('C10', 'CONSUM MATERIALE');
        $sheet->setCellValue('F10', '');
        $sheet->setCellValue('G10', '');
        $sheet->setCellValue('H10', '');
        $sheet->setCellValue('I10', 'REBUTURI');
        $sheet->setCellValue('J10', 'CONSUM+REBUT');
        $sheet->setCellValue('K10', '');
        $sheet->setCellValue('D11', 'UM');
        $sheet->setCellValue('E11', 'Consum');
        $sheet->setCellValue('F11', 'Echipament utilizat');
        $sheet->setCellValue('G11', 'Data/ora');
        $sheet->setCellValue('H11', 'Utilizator');
        $sheet->setCellValue('I11', 'Rebut');
        $sheet->setCellValue('J11', 'Total');
        $sheet->setCellValue('K11', '');

        $detailStartRow = 12;
        $detailTemplateRows = 5;
        $detailCount = max($detailRows->count(), 1);
        $targetSummaryTitleRow = $detailStartRow + $detailCount + 2;
        $summaryRowShift = $targetSummaryTitleRow - 17;
        if ($summaryRowShift > 0) {
            $sheet->insertNewRowBefore(17, $summaryRowShift);
        } elseif ($summaryRowShift < 0) {
            $sheet->removeRow($targetSummaryTitleRow, abs($summaryRowShift));
        }
        $detailEndRow = $detailStartRow + $detailCount - 1;
        for ($row = $detailStartRow; $row <= $detailEndRow; $row++) {
            $sheet->duplicateStyle($sheet->getStyle("A12:K12"), "A{$row}:K{$row}");
        }

        $productBlocks = [];
        $currentGroupKey = null;
        $currentBlockStart = null;
        foreach ($detailRows->values() as $index => $row) {
            $excelRow = $detailStartRow + $index;
            $groupKey = (string) ($row['product_group_key'] ?? $index);
            $isFirstInBlock = $groupKey !== $currentGroupKey;
            if ($isFirstInBlock) {
                if ($currentBlockStart !== null) {
                    $productBlocks[] = [$currentBlockStart, $excelRow - 1];
                }
                $currentGroupKey = $groupKey;
                $currentBlockStart = $excelRow;
            }

            $sheet->setCellValue("A{$excelRow}", $isFirstInBlock ? (string) ($row['product'] ?? '-') : '');
            $sheet->setCellValue("B{$excelRow}", $isFirstInBlock ? (string) $this->formatConsumSinteticQuantity($row['product_quantity'] ?? 0) : '');
            $sheet->setCellValue("C{$excelRow}", (string) ($row['material'] ?? '-'));
            $sheet->setCellValue("D{$excelRow}", (string) ($row['unitate_masura'] ?? ''));
            $sheet->setCellValue("E{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['consum'] ?? 0));
            $sheet->setCellValue("F{$excelRow}", (string) (($row['equipment'] ?? '-') !== '-' ? $row['equipment'] : ''));
            $sheet->setCellValue("G{$excelRow}", (string) ($row['recorded_at'] ?? '-'));
            $sheet->setCellValue("H{$excelRow}", (string) ($row['recorded_by'] ?? '-'));
            $sheet->setCellValue("I{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['rebut'] ?? 0));
            $sheet->setCellValue("J{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['total'] ?? 0));
            $sheet->setCellValue("K{$excelRow}", '');
        }

        if ($detailRows->isEmpty()) {
            $sheet->setCellValue("C{$detailStartRow}", 'Nu exista consumuri inregistrate pentru aceasta comanda.');
            $productBlocks[] = [$detailStartRow, $detailStartRow];
        } elseif ($currentBlockStart !== null) {
            $productBlocks[] = [$currentBlockStart, $detailEndRow];
        }

        $sheet->getStyle("B{$detailStartRow}:B{$detailEndRow}")->getFont()->setBold(true);
        $sheet->getStyle("E{$detailStartRow}:E{$detailEndRow}")->getFont()->setBold(true);
        $sheet->getStyle("I{$detailStartRow}:J{$detailEndRow}")->getFont()->setBold(true);

        foreach ($productBlocks as [$blockStart, $blockEnd]) {
            $sheet->getStyle("A{$blockStart}:K{$blockEnd}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_NONE,
                    ],
                    'inside' => [
                        'borderStyle' => Border::BORDER_NONE,
                    ],
                    'outline' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);
        }

        $summaryTitleRow = $targetSummaryTitleRow;
        $summaryStartRow = $summaryTitleRow + 1;
        $summaryTemplateRows = 5;
        $summaryCount = max($summaryRows->count(), 1);
        if ($summaryCount > $summaryTemplateRows) {
            $sheet->insertNewRowBefore($summaryStartRow + $summaryTemplateRows, $summaryCount - $summaryTemplateRows);
        } elseif ($summaryCount < $summaryTemplateRows) {
            $sheet->removeRow($summaryStartRow + $summaryCount, $summaryTemplateRows - $summaryCount);
        }
        $summaryEndRow = $summaryStartRow + $summaryCount - 1;
        $summaryStyleSourceRow = $summaryStartRow;
        for ($row = $summaryStartRow; $row <= $summaryEndRow; $row++) {
            $sheet->duplicateStyle($sheet->getStyle("A{$summaryStyleSourceRow}:K{$summaryStyleSourceRow}"), "A{$row}:K{$row}");
        }

        $sheet->setCellValue("A{$summaryTitleRow}", 'Centralizator CONSUM');
        $sheet->getStyle("A{$summaryTitleRow}:K{$summaryEndRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_NONE,
                ],
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        foreach ($summaryRows->values() as $index => $row) {
            $excelRow = $summaryStartRow + $index;
            $sheet->setCellValue("A{$excelRow}", $index === 0 ? 'TOTAL' : '');
            $sheet->setCellValue("C{$excelRow}", (string) ($row['material'] ?? '-'));
            $sheet->setCellValue("D{$excelRow}", (string) ($row['unitate_masura'] ?? ''));
            $sheet->setCellValue("E{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['consum'] ?? 0));
            $sheet->setCellValue("I{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['rebut'] ?? 0));
            $sheet->setCellValue("J{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['total'] ?? 0));
            $sheet->setCellValue("K{$excelRow}", '');
        }

        if ($summaryRows->isEmpty()) {
            $sheet->setCellValue("A{$summaryStartRow}", 'TOTAL');
            $sheet->setCellValue("C{$summaryStartRow}", 'Nu exista materiale de centralizat.');
        }

        $sheet->getStyle("E{$summaryStartRow}:E{$summaryEndRow}")->getFont()->setBold(true);
        $sheet->getStyle("I{$summaryStartRow}:J{$summaryEndRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$summaryStartRow}:B{$summaryEndRow}")->getFont()->setBold(true);

        return $spreadsheet;
    }

    private function buildMultipleConsumSinteticTemplateSpreadsheet(array $report): Spreadsheet
    {
        $templatePath = resource_path('excel-templates/consum-sintetic-comenzi-template.xlsx');
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Lipseste template-ul pentru exportul mai multor comenzi.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getSheet(0);

        $detailRows = collect($report['detail_rows'] ?? []);
        $summaryRows = collect($report['summary_rows'] ?? []);

        $sheet->setCellValue('G1', (string) ($report['title'] ?? 'FISA SINTETICA COMENZI-CONSUMURI'));
        $sheet->setCellValue('G2', 'PERIOADA : ' . (string) ($report['period_label'] ?? '-'));
        $sheet->setCellValue('C3', (string) ($report['order_count'] ?? 0));
        $sheet->setCellValue('C4', (string) ($report['client_count'] ?? 0));
        $sheet->setCellValue('C5', (string) ($report['new_client_count'] ?? 0));
        $sheet->setCellValue('C6', (string) ($report['generated_by'] ?? '-'));
        $sheet->setCellValue('C7', (string) (optional($report['generated_at'] ?? null)->format('d.m.Y H:i') ?? '-'));
        $sheet->getStyle('C3:C7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('C3:C7')->getFont()->setBold(true);
        $sheet->unmergeCells('C10:F10');
        $sheet->mergeCells('C10:E10');
        $sheet->setCellValue('C10', 'CONSUM MATERIALE');
        $sheet->setCellValue('F10', '');
        $sheet->setCellValue('G10', '');
        $sheet->setCellValue('H10', '');
        $sheet->setCellValue('I10', 'REBUTURI');
        $sheet->setCellValue('J10', 'CONSUM+REBUT');
        $sheet->setCellValue('K10', '');
        $sheet->setCellValue('D11', 'UM');
        $sheet->setCellValue('E11', 'Consum');
        $sheet->setCellValue('F11', 'Echipament utilizat');
        $sheet->setCellValue('G11', 'Data/ora');
        $sheet->setCellValue('H11', 'Utilizator');
        $sheet->setCellValue('I11', 'Rebut');
        $sheet->setCellValue('J11', 'Total');
        $sheet->setCellValue('K11', '');

        $detailStartRow = 12;
        $detailTemplateRows = 5;
        $detailCount = max($detailRows->count(), 1);
        $targetSummaryTitleRow = $detailStartRow + $detailCount + 2;
        $summaryRowShift = $targetSummaryTitleRow - 17;
        if ($summaryRowShift > 0) {
            $sheet->insertNewRowBefore(17, $summaryRowShift);
        } elseif ($summaryRowShift < 0) {
            $sheet->removeRow($targetSummaryTitleRow, abs($summaryRowShift));
        }

        $detailEndRow = $detailStartRow + $detailCount - 1;
        for ($row = $detailStartRow; $row <= $detailEndRow; $row++) {
            $sheet->duplicateStyle($sheet->getStyle('A12:K12'), "A{$row}:K{$row}");
        }

        $productBlocks = [];
        $currentGroupKey = null;
        $currentBlockStart = null;
        foreach ($detailRows->values() as $index => $row) {
            $excelRow = $detailStartRow + $index;
            $groupKey = (string) ($row['product_group_key'] ?? $index);
            $isFirstInBlock = $groupKey !== $currentGroupKey;
            if ($isFirstInBlock) {
                if ($currentBlockStart !== null) {
                    $productBlocks[] = [$currentBlockStart, $excelRow - 1];
                }
                $currentGroupKey = $groupKey;
                $currentBlockStart = $excelRow;
            }

            $sheet->setCellValue("A{$excelRow}", $isFirstInBlock ? (string) ($row['product'] ?? '-') : '');
            $sheet->setCellValue("B{$excelRow}", $isFirstInBlock ? (string) $this->formatConsumSinteticQuantity($row['product_quantity'] ?? 0) : '');
            $sheet->setCellValue("C{$excelRow}", (string) ($row['material'] ?? '-'));
            $sheet->setCellValue("D{$excelRow}", (string) ($row['unitate_masura'] ?? ''));
            $sheet->setCellValue("E{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['consum'] ?? 0));
            $sheet->setCellValue("F{$excelRow}", (string) (($row['equipment'] ?? '-') !== '-' ? $row['equipment'] : ''));
            $sheet->setCellValue("G{$excelRow}", (string) ($row['recorded_at'] ?? '-'));
            $sheet->setCellValue("H{$excelRow}", (string) ($row['recorded_by'] ?? '-'));
            $sheet->setCellValue("I{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['rebut'] ?? 0));
            $sheet->setCellValue("J{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['total'] ?? 0));
            $sheet->setCellValue("K{$excelRow}", '');
        }

        if ($detailRows->isEmpty()) {
            $sheet->setCellValue("C{$detailStartRow}", 'Nu exista consumuri pentru comenzile selectate.');
            $productBlocks[] = [$detailStartRow, $detailStartRow];
        } elseif ($currentBlockStart !== null) {
            $productBlocks[] = [$currentBlockStart, $detailEndRow];
        }

        $sheet->getStyle("B{$detailStartRow}:B{$detailEndRow}")->getFont()->setBold(true);
        $sheet->getStyle("E{$detailStartRow}:E{$detailEndRow}")->getFont()->setBold(true);
        $sheet->getStyle("I{$detailStartRow}:J{$detailEndRow}")->getFont()->setBold(true);

        foreach ($productBlocks as [$blockStart, $blockEnd]) {
            $sheet->getStyle("A{$blockStart}:K{$blockEnd}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_NONE,
                    ],
                    'inside' => [
                        'borderStyle' => Border::BORDER_NONE,
                    ],
                    'outline' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);
        }

        $summaryTitleRow = $targetSummaryTitleRow;
        $summaryStartRow = $summaryTitleRow + 1;
        $summaryTemplateRows = 5;
        $summaryCount = max($summaryRows->count(), 1);
        if ($summaryCount > $summaryTemplateRows) {
            $sheet->insertNewRowBefore($summaryStartRow + $summaryTemplateRows, $summaryCount - $summaryTemplateRows);
        } elseif ($summaryCount < $summaryTemplateRows) {
            $sheet->removeRow($summaryStartRow + $summaryCount, $summaryTemplateRows - $summaryCount);
        }
        $summaryEndRow = $summaryStartRow + $summaryCount - 1;
        $summaryStyleSourceRow = $summaryStartRow;
        for ($row = $summaryStartRow; $row <= $summaryEndRow; $row++) {
            $sheet->duplicateStyle($sheet->getStyle("A{$summaryStyleSourceRow}:K{$summaryStyleSourceRow}"), "A{$row}:K{$row}");
        }

        $sheet->setCellValue("A{$summaryTitleRow}", 'Centralizator CONSUM');
        $sheet->getStyle("A{$summaryTitleRow}:K{$summaryEndRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_NONE,
                ],
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        foreach ($summaryRows->values() as $index => $row) {
            $excelRow = $summaryStartRow + $index;
            $sheet->setCellValue("A{$excelRow}", $index === 0 ? 'TOTAL' : '');
            $sheet->setCellValue("C{$excelRow}", (string) ($row['material'] ?? '-'));
            $sheet->setCellValue("D{$excelRow}", (string) ($row['unitate_masura'] ?? ''));
            $sheet->setCellValue("E{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['consum'] ?? 0));
            $sheet->setCellValue("I{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['rebut'] ?? 0));
            $sheet->setCellValue("J{$excelRow}", (string) $this->formatConsumSinteticQuantity($row['total'] ?? 0));
            $sheet->setCellValue("K{$excelRow}", '');
        }

        if ($summaryRows->isEmpty()) {
            $sheet->setCellValue("A{$summaryStartRow}", 'TOTAL');
            $sheet->setCellValue("C{$summaryStartRow}", 'Nu exista materiale de centralizat.');
        }

        $sheet->getStyle("E{$summaryStartRow}:E{$summaryEndRow}")->getFont()->setBold(true);
        $sheet->getStyle("I{$summaryStartRow}:J{$summaryEndRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$summaryStartRow}:B{$summaryEndRow}")->getFont()->setBold(true);

        return $spreadsheet;
    }

    private function formatConsumSinteticDate(Carbon|string|null $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('d.m.Y');
        }

        if ($value) {
            return Carbon::parse((string) $value)->format('d.m.Y');
        }

        return '-';
    }

    private function formatConsumSinteticDateTime(Carbon|string|null $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('d.m.Y H:i');
        }

        if ($value) {
            return Carbon::parse((string) $value)->format('d.m.Y H:i');
        }

        return '-';
    }

    private function formatConsumSinteticQuantity(float|int|string|null $value): string
    {
        $formatted = number_format((float) $value, 4, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private function resolveLatestGdprConsent(Comanda $comanda): ?ComandaGdprConsent
    {
        return $comanda->gdprConsents()->latest('signed_at')->first();
    }

    private function shouldMarkComandaFinalized(?string $tip, ?string $status): bool
    {
        if (!$status) {
            return false;
        }

        if (in_array($status, StatusComanda::finalStates(), true)) {
            return true;
        }

        return $tip === TipComanda::CerereOferta->value
            && in_array($status, [
                StatusComanda::OfertaTrimisa->value,
                StatusComanda::OfertaAcceptata->value,
            ], true);
    }

    private function sendAssignmentNotificationEmails(
        Comanda $comanda,
        array $stageLabelsByUserId,
        ?User $assignedBy
    ): array {
        $summary = [
            'sent' => 0,
            'failed' => [],
            'skipped' => [],
        ];

        if ($stageLabelsByUserId === []) {
            return $summary;
        }

        $comanda->loadMissing('client');

        foreach ($stageLabelsByUserId as $userId => $stageLabels) {
            $recipient = User::find((int) $userId);
            if (!$recipient) {
                continue;
            }

            $email = trim((string) $recipient->email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $summary['skipped'][] = $recipient->name ?: ('user #' . $userId);
                continue;
            }

            try {
                Mail::to($recipient)->send(new ComandaAssignmentMail(
                    $comanda,
                    $recipient,
                    collect($stageLabels)->filter()->unique()->values()->all(),
                    $assignedBy
                ));
                $summary['sent']++;
            } catch (Throwable $exception) {
                Log::warning('Failed to send order assignment email.', [
                    'comanda_id' => $comanda->id,
                    'recipient_user_id' => $recipient->id,
                    'recipient_email' => $email,
                    'exception' => $exception->getMessage(),
                ]);

                $summary['failed'][] = $email;
            }
        }

        return $summary;
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
        if (!$user || !$user->hasAnyRole(['supervizor', 'financiar'])) {
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

        $access = $this->resolveComandaAccess($comanda, $user);
        $canWriteComenzi = $access['canWriteComenzi'];

        if (isset($scopeSet['detalii']) || isset($scopeSet['fisiere'])) {
            $statusuri = StatusComanda::options();
            $payload['header_html'] = view('comenzi.partials.header', [
                'comanda' => $comanda,
                'canWriteComenzi' => $canWriteComenzi,
                'statusuri' => $statusuri,
            ])->render();
        }

        if (isset($scopeSet['necesar']) || isset($scopeSet['consum']) || isset($scopeSet['plati'])) {
            $comanda->load([
                'produse.produs',
                'produse.consumuri.material',
                'produse.consumuri.echipament',
                'produse.consumuri.createdBy.roles',
                'produse.consumuri.updatedBy.roles',
                'plati.createdBy.roles',
                'plati.updatedBy.roles',
                'produsHistories' => fn ($query) => $query->latest()->limit(50)->with('actor.roles'),
            ]);

            $metodePlata = MetodaPlata::options();
            $statusPlataOptions = StatusPlata::options();
            $materiale = NomenclatorMaterial::query()->where('activ', true)->orderBy('denumire')->get();
            $echipamente = NomenclatorEchipament::query()->where('activ', true)->orderBy('denumire')->get();

            if (isset($scopeSet['necesar'])) {
                $payload['produse_html'] = view('comenzi.partials.necesar-table-body', [
                    'comanda' => $comanda,
                    'canWriteProduse' => $access['canWriteProduse'],
                    'canViewPreturi' => $access['canViewNecesarPrices'],
                ])->render();
                $payload['necesar_history_html'] = view('comenzi.partials.necesar-history', [
                    'histories' => $comanda->produsHistories,
                    'canViewPreturi' => $access['canViewNecesarPrices'],
                ])->render();
                $payload['counts']['necesar'] = $comanda->produse->count();
            }

            if (isset($scopeSet['consum'])) {
                $payload['consum_html'] = view('comenzi.partials.consum-content', [
                    'comanda' => $comanda,
                    'materiale' => $materiale,
                    'echipamente' => $echipamente,
                    'canWriteConsum' => $access['canWriteConsum'],
                ])->render();
                $payload['counts']['consum'] = $comanda->produse->sum(fn ($item) => $item->consumuri->count());
            }

            $payload['plati_html'] = view('comenzi.partials.plati-table-body', [
                'comanda' => $comanda,
                'metodePlata' => $metodePlata,
                'canWritePlatiCreate' => $access['canWritePlatiCreate'],
                'canWritePlatiEditExisting' => $access['canWritePlatiEditExisting'],
            ])->render();
            $payload['plati_summary_html'] = view('comenzi.partials.plati-summary', [
                'comanda' => $comanda,
                'statusPlataOptions' => $statusPlataOptions,
            ])->render();
            $payload['counts']['plati'] = $comanda->plati->count();
        }

        if (isset($scopeSet['solicitari'])) {
            $comanda->load(['solicitari.createdBy.roles']);

            $payload['solicitari_html'] = view('comenzi.partials.solicitari-existing', [
                'comanda' => $comanda,
                'canManageSolicitari' => $access['canManageSolicitari'],
                'canBypassDailyEditLock' => $access['canBypassDailyEditLock'],
            ])->render();
            $payload['counts']['solicitari'] = $comanda->solicitari->count();
        }

        if (isset($scopeSet['note'])) {
            $comanda->load(['note.createdBy.roles']);
            $noteGroups = $comanda->note->groupBy('role');

            $payload['notes_html'] = [
                'frontdesk' => view('comenzi.partials.note-existing-role', [
                    'comanda' => $comanda,
                    'notes' => $noteGroups->get('frontdesk', collect()),
                    'role' => 'frontdesk',
                    'canEditRole' => $access['canEditNotaFrontdesk'],
                    'canBypassDailyEditLock' => $access['canBypassDailyEditLock'],
                ])->render(),
                'grafician' => view('comenzi.partials.note-existing-role', [
                    'comanda' => $comanda,
                    'notes' => $noteGroups->get('grafician', collect()),
                    'role' => 'grafician',
                    'canEditRole' => $access['canEditNotaGrafician'],
                    'canBypassDailyEditLock' => $access['canBypassDailyEditLock'],
                ])->render(),
                'executant' => view('comenzi.partials.note-existing-role', [
                    'comanda' => $comanda,
                    'notes' => $noteGroups->get('executant', collect()),
                    'role' => 'executant',
                    'canEditRole' => $access['canEditNotaExecutant'],
                    'canBypassDailyEditLock' => $access['canBypassDailyEditLock'],
                ])->render(),
            ];
            $payload['counts']['note'] = $comanda->note->count();
        }

        if (isset($scopeSet['fisiere'])) {
            $comanda->load([
                'client',
                'atasamente.uploadedBy.roles',
                'facturi' => fn ($query) => $query->latest(),
                'facturi.uploadedBy.roles',
                'facturaEmails' => fn ($query) => $query->latest(),
                'mockupuri' => fn ($query) => $query->latest()->with('uploadedBy.roles'),
                'emailLogs' => fn ($query) => $query->latest(),
            ]);

            $payload['fisiere_html'] = view('comenzi.partials.fisiere-content', [
                'comanda' => $comanda,
                'canWriteAtasamente' => $access['canWriteAtasamente'],
                'canViewFacturi' => $access['canViewFacturi'],
                'canManageFacturi' => $access['canManageFacturi'],
                'canOperateFacturaFiles' => $access['canOperateFacturaFiles'],
                'canWriteMockupuri' => $access['canWriteMockupuri'],
                'canBypassDailyEditLock' => $access['canBypassDailyEditLock'],
                'mockupTypes' => Mockup::typeOptions(),
                'clientEmail' => optional($comanda->client)->email,
            ])->render();
            $payload['counts']['atasamente'] = $comanda->atasamente->count();
            $payload['counts']['facturi'] = $comanda->facturi->count();
            $payload['counts']['mockupuri'] = $comanda->mockupuri->count();
        }

        if (isset($scopeSet['detalii']) || isset($scopeSet['etape'])) {
            $comanda->load([
                'etapaHistories' => fn ($query) => $query->latest()->limit(120)->with(['etapa', 'actor.roles', 'targetUser.roles']),
            ]);

            $payload['etape_history_html'] = view('comenzi.partials.etape-history', [
                'etapeHistories' => $comanda->etapaHistories,
                'etape' => Etapa::orderBy('id')->get(),
            ])->render();
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
            $gdprResearchStatistics = $gdprConsent?->consent_research_statistics ?? false;
            $gdprOnlineCommunications = $gdprConsent?->consent_online_communications ?? false;
            $clientEmail = optional($comanda->client)->email;
            $isGdprPhysicalSource = $comanda->sursa === SursaComanda::Fizic->value;

            $payload['gdpr_status_html'] = view('comenzi.partials.gdpr-status', [
                'canWriteComenzi' => $canWriteComenzi,
                'canPreviewPdf' => $access['canPreviewPdf'] ?? false,
                'gdprHasConsent' => $gdprHasConsent,
                'comanda' => $comanda,
                'gdprSignedLabel' => $gdprSignedLabel,
                'gdprMethod' => $gdprMethod,
                'gdprMarketing' => $gdprMarketing,
                'gdprMediaMarketing' => $gdprMediaMarketing,
                'gdprResearchStatistics' => $gdprResearchStatistics,
                'gdprOnlineCommunications' => $gdprOnlineCommunications,
                'isGdprPhysicalSource' => $isGdprPhysicalSource,
                'clientEmail' => $clientEmail,
            ])->render();
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
                $cantitate = $cantitateValue === '' || $cantitateValue === null
                    ? null
                    : $this->normalizeQuantity($cantitateValue);

                return [
                    'solicitare_client' => $solicitare !== '' ? $solicitare : null,
                    'cantitate' => $cantitate,
                ];
            })
            ->filter(fn ($entry) => $entry['solicitare_client'] !== null || $entry['cantitate'] !== null)
            ->values()
            ->all();
    }

    private function resolveMaterialFromNomenclator(
        string $denumire,
        ?int $selectedMaterialId,
        bool $addToNomenclator,
        string $unitateMasura,
        ?int $userId
    ): array {
        $denumire = trim($denumire);
        $unitateMasura = trim($unitateMasura);

        if ($denumire === '') {
            throw ValidationException::withMessages([
                'material_denumire' => 'Materialul este obligatoriu.',
            ]);
        }

        if ($unitateMasura === '') {
            throw ValidationException::withMessages([
                'unitate_masura' => 'Unitatea de masura este obligatorie.',
            ]);
        }

        if ($selectedMaterialId) {
            $selected = NomenclatorMaterial::query()->find($selectedMaterialId);
            if ($selected && strcasecmp($selected->denumire, $denumire) === 0) {
                return [
                    'id' => $selected->id,
                    'denumire' => $selected->denumire,
                    'unitate_masura' => $selected->unitate_masura,
                ];
            }
        }

        $matched = NomenclatorMaterial::query()
            ->whereRaw('LOWER(denumire) = ?', [mb_strtolower($denumire)])
            ->first();

        if ($matched) {
            return [
                'id' => $matched->id,
                'denumire' => $matched->denumire,
                'unitate_masura' => $matched->unitate_masura,
            ];
        }

        if ($addToNomenclator) {
            $entry = NomenclatorMaterial::query()->create([
                'denumire' => $denumire,
                'unitate_masura' => $unitateMasura,
                'descriere' => null,
                'activ' => true,
                'created_by' => $userId,
            ]);

            return [
                'id' => $entry->id,
                'denumire' => $entry->denumire,
                'unitate_masura' => $entry->unitate_masura,
            ];
        }

        return [
            'id' => null,
            'denumire' => $denumire,
            'unitate_masura' => $unitateMasura,
        ];
    }

    private function resolveEquipmentFromNomenclator(
        string $denumire,
        ?int $selectedEquipmentId,
        bool $addToNomenclator,
        ?int $userId
    ): array {
        $denumire = trim($denumire);

        if ($denumire === '') {
            return [
                'id' => null,
                'denumire' => null,
            ];
        }

        if ($selectedEquipmentId) {
            $selected = NomenclatorEchipament::query()->find($selectedEquipmentId);
            if ($selected && strcasecmp($selected->denumire, $denumire) === 0) {
                return [
                    'id' => $selected->id,
                    'denumire' => $selected->denumire,
                ];
            }
        }

        $matched = NomenclatorEchipament::query()
            ->whereRaw('LOWER(denumire) = ?', [mb_strtolower($denumire)])
            ->first();

        if ($matched) {
            return [
                'id' => $matched->id,
                'denumire' => $matched->denumire,
            ];
        }

        if ($addToNomenclator) {
            $entry = NomenclatorEchipament::query()->create([
                'denumire' => $denumire,
                'activ' => true,
                'created_by' => $userId,
            ]);

            return [
                'id' => $entry->id,
                'denumire' => $entry->denumire,
            ];
        }

        return [
            'id' => null,
            'denumire' => $denumire,
        ];
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

    private function resolveComandaAccess(Comanda $comanda, ?User $user): array
    {
        $isCerereOferta = $this->isCerereOferta($comanda);
        $canWriteComenzi = $user?->hasPermission('comenzi.write') ?? false;
        $canWriteProdusePermission = $user?->hasPermission('comenzi.produse.write') ?? false;
        $canWriteAtasamentePermission = $user?->hasPermission('comenzi.atasamente.write') ?? false;
        $canWriteMockupuriPermission = $user?->hasPermission('comenzi.mockupuri.write') ?? false;
        $canWritePlatiPermission = $user?->hasPermission('comenzi.plati.write') ?? false;
        $canWriteEtapePermission = $user?->hasPermission('comenzi.etape.write') ?? false;
        $canManageFacturiBase = $comanda->canManageFacturi($user);

        $canViewNecesarPrices = $comanda->canViewNecesarPrices($user);
        $canWriteProduse = $canWriteProdusePermission && $comanda->canEditNecesar($user);
        $canWriteConsum = $canWriteProdusePermission;
        $canWritePlatiCreate = $canWritePlatiPermission && $comanda->canCreatePlati($user);
        $canWritePlatiEditExisting = $canWritePlatiPermission && $comanda->canEditExistingPlati($user);
        $canManageOrderFiles = $comanda->canManageOrderFiles($user);
        $canWriteAtasamente = $canWriteAtasamentePermission && $canManageOrderFiles;
        $canWriteMockupuri = $canWriteMockupuriPermission && $canManageOrderFiles;

        $access = [
            'isCerereOferta' => $isCerereOferta,
            'canWriteComenzi' => $canWriteComenzi,
            'canWriteEtape' => $canWriteEtapePermission,
            'canEditAssignments' => $canWriteComenzi && $comanda->canEditAssignments($user),
            'canManageSolicitari' => $canWriteComenzi && $comanda->canManageSolicitari($user),
            'canEditNotaFrontdesk' => $canWriteComenzi && $comanda->canEditNotaFrontdesk($user),
            'canEditNotaGrafician' => $canWriteComenzi && $comanda->canEditNotaGrafician($user),
            'canEditNotaExecutant' => $canWriteComenzi && $comanda->canEditNotaExecutant($user),
            'canWriteProduse' => $canWriteProduse,
            'canWriteConsum' => $canWriteConsum,
            'canViewNecesarPrices' => $canViewNecesarPrices,
            'canWriteAtasamente' => $canWriteAtasamente,
            'canWriteMockupuri' => $canWriteMockupuri,
            'canWritePlatiCreate' => $canWritePlatiCreate,
            'canWritePlatiEditExisting' => $canWritePlatiEditExisting,
            'canBypassDailyEditLock' => $this->canBypassDailyEditLock($user),
            'canViewFacturi' => $comanda->canViewFacturi($user),
            'canManageFacturi' => $canManageFacturiBase,
            'canOperateFacturaFiles' => $user?->hasAnyRole(['supervizor', 'financiar']) ?? false,
            'canEditMockupTiparFlags' => $canWriteComenzi && !$isCerereOferta,
            'canDownloadInternalDocs' => !$isCerereOferta && $canManageOrderFiles,
            'canDownloadOfertaPdf' => $comanda->canAccessOfertaPrices($user),
            'canPreviewPdf' => $this->canUsePdfPreview($user),
        ];

        if ($isCerereOferta) {
            $access['canWriteAtasamente'] = false;
            $access['canWriteMockupuri'] = false;
            $access['canWritePlatiCreate'] = false;
            $access['canWritePlatiEditExisting'] = false;
            $access['canManageFacturi'] = false;
            $access['canEditNotaGrafician'] = false;
            $access['canEditNotaExecutant'] = false;
            $access['canDownloadInternalDocs'] = false;
        }

        return $access;
    }

    private function canUsePdfPreview(?User $user): bool
    {
        return $user?->hasAnyRole(['superadmin']) ?? false;
    }

    private function formatProdusState(ComandaProdus $linie): array
    {
        $linie->loadMissing('produs');

        return [
            'linie_id' => (int) $linie->id,
            'produs_id' => $linie->produs_id ? (int) $linie->produs_id : null,
            'denumire' => $linie->custom_denumire ?: ($linie->produs?->denumire ?? null),
            'descriere' => $linie->descriere,
            'cantitate' => (float) $linie->cantitate,
            'pret_unitar' => (float) $linie->pret_unitar,
            'total_linie' => (float) $linie->total_linie,
        ];
    }

    private function normalizeQuantity(mixed $value): float
    {
        return round((float) $value, 4);
    }

    private function logProdusHistory(
        Comanda $comanda,
        ?int $actorUserId,
        string $action,
        ?ComandaProdus $linie,
        ?array $before,
        ?array $after
    ): void {
        ComandaProdusHistory::create([
            'comanda_id' => $comanda->id,
            'comanda_produs_id' => $linie?->id,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'changes' => [
                'before' => $before,
                'after' => $after,
            ],
        ]);
    }

    private function logEtapaHistory(
        Comanda $comanda,
        ?int $actorUserId,
        ?int $etapaId,
        ?int $targetUserId,
        string $action,
        ?string $statusBefore,
        ?string $statusAfter,
        ?array $changes
    ): void {
        ComandaEtapaHistory::create([
            'comanda_id' => $comanda->id,
            'etapa_id' => $etapaId,
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'changes' => $changes,
        ]);
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
        $nrComandaInput = trim((string) $request->get('nr_comanda', ''));
        $nrComandaInvalid = $nrComandaInput !== '' && !ctype_digit($nrComandaInput);
        $nrComanda = ($nrComandaInput !== '' && ctype_digit($nrComandaInput))
            ? (int) $nrComandaInput
            : null;
        $dataDe = $request->timp_de;
        $dataPana = $request->timp_pana;
        $overdue = $request->boolean('overdue');
        $dueSoon = $request->boolean('due_soon');
        $asignateMie = $request->boolean('asignate_mie');
        $inAsteptare = $request->boolean('in_asteptare');
        $inAsteptareAll = $request->boolean('in_asteptare_all');
        $operationalOpen = $request->boolean('operational_open');
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
            ->when($operationalOpen, fn ($query) => $query->operationallyOpen())
            ->when($tip, fn ($query) => $query->where('tip', $tip))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($sursa, fn ($query) => $query->where('sursa', $sursa))
            ->when($nrComanda !== null, fn ($query) => $query->where('comenzi.id', $nrComanda))
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

        if ($nrComandaInvalid) {
            $query->whereRaw('1=0');
        }

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
            'nrComanda' => $nrComandaInput,
            'dataDe' => $dataDe,
            'dataPana' => $dataPana,
            'overdue' => $overdue,
            'dueSoon' => $dueSoon,
            'asignateMie' => $asignateMie,
            'inAsteptare' => $inAsteptare,
            'inAsteptareAll' => $inAsteptareAll,
            'operationalOpen' => $operationalOpen,
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

            $cantitate = isset($cantitati[$index]) ? $this->normalizeQuantity($cantitati[$index]) : 0;
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

