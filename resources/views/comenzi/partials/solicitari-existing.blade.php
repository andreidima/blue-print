@php
    $canManageSolicitari = $canManageSolicitari ?? false;
    $canBypassDailyEditLock = $canBypassDailyEditLock ?? false;
    $lockTimezone = (string) config('app.timezone', 'UTC');
    $lockNow = now($lockTimezone);
    $resolveRoleMeta = function (?App\Models\User $user): array {
        $role = $user?->primaryActiveRole();
        return [
            'name' => $role?->name ?? 'Utilizator',
            'color' => $role?->color ?? '#6c757d',
        ];
    };
    $formatQuantity = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = number_format((float) $value, 4, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    };
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <div class="fw-semibold">Solicitari existente</div>
    <span class="badge bg-secondary">{{ $comanda->solicitari->count() }}</span>
</div>
@forelse ($comanda->solicitari as $solicitare)
    @php
        $lockedAt = $solicitare->created_at
            ? $solicitare->created_at->copy()->setTimezone($lockTimezone)->startOfDay()->addDay()
            : null;
        $isLocked = $lockedAt ? $lockNow->gte($lockedAt) : false;
        $canEditCurrent = $canManageSolicitari && (!$isLocked || $canBypassDailyEditLock);
        $actorMeta = $resolveRoleMeta($solicitare->createdBy);
    @endphp
    <div class="accordion mb-2" id="informatii-item-{{ $solicitare->id }}">
        <div class="accordion-item border rounded-3" style="border-left: 4px solid {{ $actorMeta['color'] }} !important;">
            <h2 class="accordion-header" id="heading-solicitare-{{ $solicitare->id }}">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-solicitare-{{ $solicitare->id }}" aria-expanded="false" aria-controls="collapse-solicitare-{{ $solicitare->id }}">
                    <span class="fw-semibold">Solicitare #{{ $loop->iteration }}</span>
                    <span class="ms-2 text-muted small">{{ optional($solicitare->created_at)->format('d.m.Y H:i') }}</span>
                    <span class="badge ms-2" style="background-color: {{ $actorMeta['color'] }};">{{ $actorMeta['name'] }}</span>
                </button>
            </h2>
            <div id="collapse-solicitare-{{ $solicitare->id }}" class="accordion-collapse collapse" aria-labelledby="heading-solicitare-{{ $solicitare->id }}">
                <div class="accordion-body">
                    @if ($canEditCurrent)
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-7">
                                <form id="solicitare-update-{{ $solicitare->id }}" method="POST" action="{{ route('comenzi.solicitari.update', [$comanda, $solicitare]) }}" data-ajax-form data-ajax-scope="solicitari">
                                    @method('PUT')
                                    @csrf
                                    <label class="mb-0 ps-3">Solicitare client</label>
                                    <textarea class="form-control bg-white rounded-3" name="solicitare_client" rows="3">{{ $solicitare->solicitare_client }}</textarea>
                                </form>
                            </div>
                            <div class="col-lg-3">
                                <label class="mb-0 ps-3">Cantitate</label>
                                <input type="number" min="0.0001" step="0.0001" class="form-control bg-white rounded-3" name="cantitate" value="{{ $formatQuantity($solicitare->cantitate) }}" form="solicitare-update-{{ $solicitare->id }}">
                            </div>
                            <div class="col-lg-2 d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-sm btn-primary text-white" form="solicitare-update-{{ $solicitare->id }}">
                                    <i class="fa-solid fa-save me-1"></i> Salveaza
                                </button>
                                <form method="POST" action="{{ route('comenzi.solicitari.destroy', [$comanda, $solicitare]) }}" data-confirm="Sigur vrei sa stergi aceasta solicitare?" data-ajax-form data-ajax-scope="solicitari">
                                    @method('DELETE')
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash me-1"></i> Sterge
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="row g-3">
                            <div class="col-lg-8">
                                <div class="small text-muted mb-1">Solicitare client</div>
                                <div class="fw-semibold">{!! $solicitare->solicitare_client ? nl2br(e($solicitare->solicitare_client)) : '-' !!}</div>
                            </div>
                            <div class="col-lg-4">
                                <div class="small text-muted mb-1">Cantitate</div>
                                <div class="fw-semibold">{{ $solicitare->cantitate !== null ? $formatQuantity($solicitare->cantitate) : '-' }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="row g-3 mt-2">
                        <div class="col-lg-8">
                            <div class="small text-muted mb-1">Adaugat de</div>
                            <div class="fw-semibold">{{ optional($solicitare->createdBy)->name ?? $solicitare->created_by_label ?? '-' }}</div>
                        </div>
                        <div class="col-lg-4">
                            <div class="small text-muted mb-1">Data</div>
                            <div class="fw-semibold">{{ optional($solicitare->created_at)->format('d.m.Y H:i') ?? '-' }}</div>
                            @if ($lockedAt)
                                @if ($isLocked)
                                    <div class="small mt-2">Blocat din {{ $lockedAt->format('d.m.Y H:i') }}.</div>
                                @else
                                    <div class="small mt-2">Se blocheaza la {{ $lockedAt->format('d.m.Y H:i') }}.</div>
                                @endif
                            @endif
                            @if ($solicitare->updated_at && $solicitare->updated_at->ne($solicitare->created_at))
                                <div class="small text-muted mt-2">Actualizat</div>
                                <div class="fw-semibold">{{ $solicitare->updated_at->format('d.m.Y H:i') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="text-muted small">Nu exista solicitari adaugate.</div>
@endforelse
