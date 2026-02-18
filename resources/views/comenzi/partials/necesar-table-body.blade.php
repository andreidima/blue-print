@php
    $canWriteProduse = $canWriteProduse ?? (auth()->user()?->hasPermission('comenzi.produse.write') ?? false);
@endphp
@forelse ($comanda->produse as $linie)
    @php
        $updateFormId = 'produs-update-' . $linie->id;
    @endphp
    <tr>
        <td>{{ $linie->custom_denumire ?? ($linie->produs->denumire ?? '-') }}</td>
        <td>
            @if ($canWriteProduse)
                <textarea
                    class="form-control form-control-sm"
                    name="descriere"
                    rows="2"
                    form="{{ $updateFormId }}"
                    placeholder="Descriere"
                >{{ $linie->descriere }}</textarea>
            @else
                {{ $linie->descriere ?: '-' }}
            @endif
        </td>
        <td>
            @if ($canWriteProduse)
                <input
                    type="number"
                    min="1"
                    class="form-control form-control-sm"
                    name="cantitate"
                    value="{{ $linie->cantitate }}"
                    form="{{ $updateFormId }}"
                    required
                >
            @else
                {{ $linie->cantitate }}
            @endif
        </td>
        <td>
            @if ($canWriteProduse)
                <input
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control form-control-sm"
                    name="pret_unitar"
                    value="{{ number_format((float) $linie->pret_unitar, 2, '.', '') }}"
                    form="{{ $updateFormId }}"
                    required
                >
            @else
                {{ number_format($linie->pret_unitar, 2) }}
            @endif
        </td>
        <td>{{ number_format($linie->total_linie, 2) }}</td>
        <td class="text-end">
            @if ($canWriteProduse)
                <form id="{{ $updateFormId }}" method="POST" action="{{ route('comenzi.produse.update', [$comanda, $linie]) }}" data-ajax-form data-ajax-scope="necesar" class="d-inline">
                    @csrf
                    @method('PUT')
                </form>
                <button
                    type="submit"
                    form="{{ $updateFormId }}"
                    class="btn btn-sm btn-primary"
                    title="Salveaza linia"
                    aria-label="Salveaza linia"
                >
                    <i class="fa-solid fa-save"></i>
                </button>
                <form method="POST" action="{{ route('comenzi.produse.destroy', [$comanda, $linie]) }}" data-ajax-form data-ajax-scope="necesar" data-confirm="Sigur vrei sa elimini produsul?" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger" title="Elimina produs" aria-label="Elimina produs">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="text-center text-muted">Nu exista produse adaugate.</td>
    </tr>
@endforelse
