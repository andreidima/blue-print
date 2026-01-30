@forelse ($comanda->plati as $plata)
    <tr>
        <td>{{ optional($plata->platit_la)->format('d.m.Y H:i') }}</td>
        <td>{{ number_format($plata->suma, 2) }}</td>
        <td>{{ $metodePlata[$plata->metoda] ?? $plata->metoda }}</td>
        <td>{{ $plata->numar_factura }}</td>
        <td>{{ $plata->note }}</td>
        <td class="text-end">
            <form method="POST" action="{{ route('comenzi.plati.destroy', [$comanda, $plata]) }}" data-ajax-form data-ajax-scope="plati" data-confirm="Sigur vrei sa elimini plata?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger" title="Elimina plata" aria-label="Elimina plata">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </form>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="text-center text-muted">Nu exista plati.</td>
    </tr>
@endforelse
