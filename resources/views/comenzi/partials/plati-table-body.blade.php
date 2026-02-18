@php
    $canWritePlati = $canWritePlati ?? (auth()->user()?->hasPermission('comenzi.plati.write') ?? false);
@endphp
@forelse ($comanda->plati as $plata)
    @php
        $updateFormId = 'plata-update-' . $plata->id;
    @endphp
    <tr>
        <td>
            @if ($canWritePlati)
                <input
                    type="datetime-local"
                    class="form-control form-control-sm"
                    name="platit_la"
                    value="{{ optional($plata->platit_la)->format('Y-m-d\\TH:i') }}"
                    form="{{ $updateFormId }}"
                    required
                >
            @else
                {{ optional($plata->platit_la)->format('d.m.Y H:i') }}
            @endif
        </td>
        <td>
            @if ($canWritePlati)
                <input
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="form-control form-control-sm"
                    name="suma"
                    value="{{ number_format((float) $plata->suma, 2, '.', '') }}"
                    form="{{ $updateFormId }}"
                    required
                >
            @else
                {{ number_format($plata->suma, 2) }}
            @endif
        </td>
        <td>
            @if ($canWritePlati)
                <select class="form-select form-select-sm" name="metoda" form="{{ $updateFormId }}" required>
                    @foreach ($metodePlata as $key => $label)
                        <option value="{{ $key }}" {{ $plata->metoda === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            @else
                {{ $metodePlata[$plata->metoda] ?? $plata->metoda }}
            @endif
        </td>
        <td>
            @if ($canWritePlati)
                <input
                    type="text"
                    class="form-control form-control-sm"
                    name="numar_factura"
                    value="{{ $plata->numar_factura }}"
                    form="{{ $updateFormId }}"
                    placeholder="Nr. factura"
                >
            @else
                {{ $plata->numar_factura }}
            @endif
        </td>
        <td>
            @if ($canWritePlati)
                <input
                    type="text"
                    class="form-control form-control-sm"
                    name="note"
                    value="{{ $plata->note }}"
                    form="{{ $updateFormId }}"
                    placeholder="Note"
                >
            @else
                {{ $plata->note }}
            @endif
        </td>
        <td class="text-end">
            @if ($canWritePlati)
                <form id="{{ $updateFormId }}" method="POST" action="{{ route('comenzi.plati.update', [$comanda, $plata]) }}" data-ajax-form data-ajax-scope="plati" class="d-inline">
                    @csrf
                    @method('PUT')
                </form>
                <button
                    type="submit"
                    form="{{ $updateFormId }}"
                    class="btn btn-sm btn-primary"
                    title="Salveaza plata"
                    aria-label="Salveaza plata"
                >
                    <i class="fa-solid fa-save"></i>
                </button>
                <form method="POST" action="{{ route('comenzi.plati.destroy', [$comanda, $plata]) }}" data-ajax-form data-ajax-scope="plati" data-confirm="Sigur vrei sa elimini plata?" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger" title="Elimina plata" aria-label="Elimina plata">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="text-center text-muted">Nu exista plati.</td>
    </tr>
@endforelse
