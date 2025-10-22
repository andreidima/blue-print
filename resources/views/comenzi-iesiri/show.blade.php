@extends('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px;">
  <div class="row card-header align-items-center" style="border-radius:40px 40px 0 0;">
    <div class="col-lg-9">
      <span class="badge culoare1 fs-5">
        <i class="fa-solid fa-receipt me-1"></i>
        Comanda: {{ $nr_comanda }}
      </span>
      <div class="mt-2 text-white">
        Perioadă: {{ $data_inceput->format('d.m.Y H:i') }}
        – {{ $data_sfarsit->format('d.m.Y H:i') }}
      </div>
      <div class="mt-2">
        <span class="badge {{ $fulfillment['badge'] }}">{{ $fulfillment['label'] }}</span>
        @if($ordered_qty > 0)
          <span class="text-white small ms-2">{{ $fulfilled_qty }} / {{ $ordered_qty }} produse livrate</span>
        @endif
      </div>
    </div>
    <div class="col-lg-3 text-end">
      <a
        href="{{ route('comenzi.iesiri.pdf', $nr_comanda) }}"
        target="_blank"
        class="btn btn-sm bg-primary text-white"
        title="PDF">
        <i class="fa-solid fa-file-pdf me-1"></i>PDF
      </a>
    </div>
  </div>
  <div class="card-body px-0 py-3">
    <div class="table-responsive rounded">
      <table class="table table-striped table-hover rounded">
        <thead class="text-white">
          <tr>
            <th class="text-white culoare2">#</th>
            <th class="text-white culoare2">Produs</th>
            <th class="text-white culoare2">Cantitate</th>
            <th class="text-white culoare2">SKU</th>
            <th class="text-white culoare2">Data</th>
            <th class="text-white culoare2">Utilizator</th>
          </tr>
        </thead>
        <tbody>
          @forelse($movements as $m)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $m->produs?->nume ?? '—' }}</td>
            <td>{{ abs($m->delta) }}</td>
            <td>{{ $m->orderItem?->sku ?? $m->produs?->sku ?? '—' }}</td>
            <td>{{ $m->created_at->format('d.m.Y H:i') }}</td>
            <td>{{ $m->user?->name ?? '—' }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center text-muted py-3">
              Nicio ieșire pentru această comandă.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="text-center">
        <a href="{{ Session::get('returnUrl', route('comenzi.iesiri.index')) }}" class="btn btn-secondary rounded-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Înapoi
        </a>
    </div>
  </div>
</div>
@endsection
