@php
    $mockupTypes = $mockupTypes ?? \App\Models\Mockup::typeOptions();
    $latestMockupsByType = $latestMockupsByType ?? [];
    $selectedMockupLinkTypes = collect($selectedMockupLinkTypes ?? [])
        ->map(fn ($value) => (string) $value)
        ->all();
    $inputIdPrefix = $inputIdPrefix ?? 'email-mockup-attachments';
@endphp

<div class="mb-3">
    <div class="small fw-semibold mb-1">Trimite linkuri fisiere info (optional)</div>
    <div class="small text-muted mb-2">Pentru fiecare rubrica selectata se trimite link catre ultimul fisier incarcat.</div>
    <div class="row g-2">
        @foreach ($mockupTypes as $type => $label)
            @php
                $fieldId = $inputIdPrefix . '-' . $type;
                $latestFile = $latestMockupsByType[$type] ?? null;
            @endphp
            <div class="col-lg-6">
                <div class="form-check border rounded-3 px-3 py-2 h-100">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="mockup_link_types[]"
                        value="{{ $type }}"
                        id="{{ $fieldId }}"
                        {{ in_array($type, $selectedMockupLinkTypes, true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label w-100" for="{{ $fieldId }}">
                        <span class="fw-semibold d-block">{{ $label }}</span>
                        @if ($latestFile)
                            <span class="small text-muted d-block">
                                Ultimul: {{ $latestFile->original_name }}
                                @if ($latestFile->created_at)
                                    ({{ $latestFile->created_at->format('d.m.Y H:i') }})
                                @endif
                            </span>
                        @else
                            <span class="small text-muted d-block">Fara fisiere incarcate.</span>
                        @endif
                    </label>
                </div>
            </div>
        @endforeach
    </div>
</div>
