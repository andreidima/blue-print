@php
    $histories = $histories ?? collect();
    $canViewPreturi = $canViewPreturi ?? true;
    $actionLabels = [
        'created' => 'Adaugat',
        'updated' => 'Actualizat',
        'deleted' => 'Sters',
    ];
@endphp

<div class="p-3 rounded-3 bg-light border">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fw-semibold">Istoric modificari necesar</div>
        <span class="badge bg-secondary">{{ $histories->count() }}</span>
    </div>
    @forelse ($histories as $history)
        @php
            $changes = is_array($history->changes) ? $history->changes : [];
            $before = is_array($changes['before'] ?? null) ? $changes['before'] : [];
            $after = is_array($changes['after'] ?? null) ? $changes['after'] : [];
            $label = $actionLabels[$history->action] ?? ucfirst((string) $history->action);
            $denumire = $after['denumire'] ?? $before['denumire'] ?? 'Produs';
            $beforeCantitate = $before['cantitate'] ?? null;
            $afterCantitate = $after['cantitate'] ?? null;
            $beforePret = $before['pret_unitar'] ?? null;
            $afterPret = $after['pret_unitar'] ?? null;
        @endphp
        <div class="border rounded-3 p-2 mb-2 bg-white">
            <div class="small text-muted">
                {{ optional($history->created_at)->format('d.m.Y H:i') ?? '-' }} -
                {{ optional($history->actor)->name ?? 'Sistem' }} -
                {{ $label }}
            </div>
            <div class="fw-semibold">{{ $denumire }}</div>
            <div class="small">
                @if ($history->action === 'updated')
                    Cantitate: {{ $beforeCantitate ?? '-' }} -> {{ $afterCantitate ?? '-' }}
                    @if ($canViewPreturi)
                        | Pret: {{ $beforePret !== null ? number_format((float) $beforePret, 2) : '-' }} -> {{ $afterPret !== null ? number_format((float) $afterPret, 2) : '-' }}
                    @endif
                @elseif ($history->action === 'created')
                    Cantitate: {{ $afterCantitate ?? '-' }}
                    @if ($canViewPreturi)
                        | Pret: {{ $afterPret !== null ? number_format((float) $afterPret, 2) : '-' }}
                    @endif
                @elseif ($history->action === 'deleted')
                    Cantitate: {{ $beforeCantitate ?? '-' }}
                    @if ($canViewPreturi)
                        | Pret: {{ $beforePret !== null ? number_format((float) $beforePret, 2) : '-' }}
                    @endif
                @endif
            </div>
        </div>
    @empty
        <div class="small text-muted">Nu exista modificari inregistrate.</div>
    @endforelse
</div>
