<div class="row card-header align-items-center comanda-header">
    <div class="col-lg-8">
        <span class="badge culoare1 fs-5">
            <i class="fa-solid fa-clipboard-list me-1"></i> Comanda #{{ $comanda->id }}
        </span>
        <span class="badge bg-secondary">{{ $statusuri[$comanda->status] ?? $comanda->status }}</span>
        @if ($comanda->is_overdue)
            <span class="badge bg-danger">Intarziata</span>
        @elseif ($comanda->is_due_soon)
            <span class="badge bg-warning text-dark">In urmatoarele 24h</span>
        @endif
        @if ($comanda->finalizat_la)
            <span class="badge {{ $comanda->is_late ? 'bg-danger' : 'bg-success' }}">
                Finalizat {{ $comanda->finalizat_la->format('d.m.Y H:i') }}
            </span>
        @endif
    </div>
    <div class="col-lg-4 text-end">
        @if ($canWriteComenzi)
            <form method="POST" action="{{ route('comenzi.destroy', $comanda) }}" class="d-inline" onsubmit="return confirm('Sigur vrei sa stergi aceasta comanda?')">
                @method('DELETE')
                @csrf
                <button type="submit" class="btn btn-sm btn-danger text-white rounded-3 shadow-sm me-2">
                    <i class="fa-solid fa-trash me-1"></i> Sterge
                </button>
            </form>
        @endif
        <a class="btn btn-sm btn-outline-light rounded-3 shadow-sm" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
            <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
        </a>
    </div>
</div>
