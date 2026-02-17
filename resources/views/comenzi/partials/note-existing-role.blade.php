@php
    $roleLabels = [
        'frontdesk' => 'Note frontdesk',
        'grafician' => 'Note grafician',
        'executant' => 'Note executant',
    ];
    $roleLabel = $roleLabels[$role] ?? 'Note';
    $canBypassDailyEditLock = $canBypassDailyEditLock ?? false;
    $lockTimezone = (string) config('app.timezone', 'UTC');
    $lockNow = now($lockTimezone);
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <div class="fw-semibold">{{ $roleLabel }}</div>
    <span class="badge bg-secondary">{{ $notes->count() }}</span>
</div>

@forelse ($notes as $nota)
    @php
        $lockedAt = $nota->created_at
            ? $nota->created_at->copy()->setTimezone($lockTimezone)->startOfDay()->addDay()
            : null;
        $isLocked = $lockedAt ? $lockNow->gte($lockedAt) : false;
        $canEditCurrent = $canEditRole && (!$isLocked || $canBypassDailyEditLock);
    @endphp
    <div class="accordion mb-2" id="note-{{ $role }}-{{ $nota->id }}">
        <div class="accordion-item border rounded-3">
            <h2 class="accordion-header" id="heading-note-{{ $role }}-{{ $nota->id }}">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-note-{{ $role }}-{{ $nota->id }}" aria-expanded="false" aria-controls="collapse-note-{{ $role }}-{{ $nota->id }}">
                    <span class="fw-semibold">Nota #{{ $loop->iteration }}</span>
                    <span class="ms-2 text-muted small">{{ optional($nota->created_at)->format('d.m.Y H:i') }}</span>
                </button>
            </h2>
            <div id="collapse-note-{{ $role }}-{{ $nota->id }}" class="accordion-collapse collapse" aria-labelledby="heading-note-{{ $role }}-{{ $nota->id }}">
                <div class="accordion-body">
                    @if ($canEditCurrent)
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-9">
                                <form id="note-update-{{ $nota->id }}" method="POST" action="{{ route('comenzi.note.update', [$comanda, $nota]) }}" data-ajax-form data-ajax-scope="note">
                                    @method('PUT')
                                    @csrf
                                    <label class="mb-0 ps-3">Nota</label>
                                    <textarea class="form-control bg-white rounded-3" name="nota" rows="3">{{ $nota->nota }}</textarea>
                                </form>
                            </div>
                            <div class="col-lg-3 d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-sm btn-primary text-white" form="note-update-{{ $nota->id }}">
                                    <i class="fa-solid fa-save me-1"></i> Salveaza
                                </button>
                                <form method="POST" action="{{ route('comenzi.note.destroy', [$comanda, $nota]) }}" data-confirm="Sigur vrei sa stergi aceasta nota?" data-ajax-form data-ajax-scope="note">
                                    @method('DELETE')
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash me-1"></i> Sterge
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="fw-semibold">{!! $nota->nota ? nl2br(e($nota->nota)) : '-' !!}</div>
                    @endif

                    <div class="row g-3 mt-2">
                        <div class="col-lg-8">
                            <div class="small text-muted mb-1">Adaugat de</div>
                            <div class="fw-semibold">{{ optional($nota->createdBy)->name ?? $nota->created_by_label ?? '-' }}</div>
                        </div>
                        <div class="col-lg-4">
                            <div class="small text-muted mb-1">Data</div>
                            <div class="fw-semibold">{{ optional($nota->created_at)->format('d.m.Y H:i') ?? '-' }}</div>
                            @if ($lockedAt)
                                @if ($isLocked)
                                    <div class="small mt-2">Blocat din {{ $lockedAt->format('d.m.Y H:i') }}.</div>
                                @else
                                    <div class="small mt-2">Se blocheaza la {{ $lockedAt->format('d.m.Y H:i') }}.</div>
                                @endif
                            @endif
                            @if ($nota->updated_at && $nota->updated_at->ne($nota->created_at))
                                <div class="small text-muted mt-2">Actualizat</div>
                                <div class="fw-semibold">{{ $nota->updated_at->format('d.m.Y H:i') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="text-muted small">Nu exista note adaugate.</div>
@endforelse
