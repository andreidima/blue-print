@php
    $etapeHistories = $etapeHistories ?? collect();
    $etape = $etape ?? collect();
    $actionLabels = [
        'assigned' => 'Asignat',
        'removed' => 'Eliminat',
        'approved' => 'Aprobat',
    ];
    $historiesByEtapa = $etapeHistories->groupBy(fn ($entry) => (string) ($entry->etapa_id ?? 'fara-etapa'));
@endphp

<div class="p-3 rounded-3 bg-light border">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fw-semibold">Istoric etape</div>
        <span class="badge bg-secondary">{{ $etapeHistories->count() }}</span>
    </div>
    <div class="accordion" id="etape-history-accordion">
        @foreach ($etape as $etapa)
            @php
                $items = $historiesByEtapa->get((string) $etapa->id, collect());
            @endphp
            <div class="accordion-item mb-2 border rounded-3">
                <h2 class="accordion-header" id="heading-etapa-history-{{ $etapa->id }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-etapa-history-{{ $etapa->id }}" aria-expanded="false" aria-controls="collapse-etapa-history-{{ $etapa->id }}">
                        {{ $etapa->label }}
                        <span class="badge bg-secondary ms-2">{{ $items->count() }}</span>
                    </button>
                </h2>
                <div id="collapse-etapa-history-{{ $etapa->id }}" class="accordion-collapse collapse" aria-labelledby="heading-etapa-history-{{ $etapa->id }}">
                    <div class="accordion-body">
                        @forelse ($items as $entry)
                            @php
                                $label = $actionLabels[$entry->action] ?? ucfirst((string) $entry->action);
                            @endphp
                            <div class="border rounded-3 p-2 mb-2 bg-white">
                                <div class="small text-muted">
                                    {{ optional($entry->created_at)->format('d.m.Y H:i') ?? '-' }} -
                                    {{ optional($entry->actor)->name ?? 'Sistem' }}
                                </div>
                                <div class="fw-semibold">{{ $label }}</div>
                                <div class="small">
                                    Utilizator: {{ optional($entry->targetUser)->name ?? '-' }}
                                    @if ($entry->status_before || $entry->status_after)
                                        | Status: {{ $entry->status_before ?? '-' }} -> {{ $entry->status_after ?? '-' }}
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="small text-muted">Fara modificari pe aceasta etapa.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
