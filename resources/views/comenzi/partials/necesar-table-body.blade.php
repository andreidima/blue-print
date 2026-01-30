@forelse ($comanda->produse as $linie)
    <tr>
        <td>{{ $linie->custom_denumire ?? ($linie->produs->denumire ?? '-') }}</td>
        <td>{{ $linie->cantitate }}</td>
        <td>{{ number_format($linie->pret_unitar, 2) }}</td>
        <td>{{ number_format($linie->total_linie, 2) }}</td>
        <td class="text-end">
            <form method="POST" action="{{ route('comenzi.produse.destroy', [$comanda, $linie]) }}" data-ajax-form data-ajax-scope="necesar" data-confirm="Sigur vrei sa elimini produsul?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger" title="Elimina produs" aria-label="Elimina produs">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </form>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5" class="text-center text-muted">Nu exista produse adaugate.</td>
    </tr>
@endforelse
