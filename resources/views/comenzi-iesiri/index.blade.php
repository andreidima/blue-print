@extends('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px;">
  <div class="row card-header align-items-center" style="border-radius:40px 40px 0 0;">
    <div class="col-lg-3">
      <span class="badge culoare1 fs-5">
        <i class="fa-solid fa-receipt me-1"></i> Comenzi de ieșiri
      </span>
    </div>
    <div class="col-lg-6">
      @php
        $currentSort = request('sort');
        $currentDirection = request('direction', 'asc');
        $buildSortUrl = function (string $field) use ($currentSort, $currentDirection) {
            $direction = $currentSort === $field && $currentDirection === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery(array_merge(request()->except('page'), [
                'sort' => $field,
                'direction' => $direction,
            ]));
        };
        $sortIcon = function (string $field) use ($currentSort, $currentDirection) {
            if ($currentSort !== $field) {
                return 'fa-sort';
            }

            return $currentDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        };
      @endphp
      <form class="needs-validation" novalidate method="GET" action="{{ route('comenzi.iesiri.index') }}">
        @csrf
        <input type="hidden" name="sort" value="{{ request('sort') }}">
        <input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">
        <div class="row mb-2 custom-search-form justify-content-center">
          <div class="col-lg-6">
            <input
              type="text"
              class="form-control rounded-3"
              id="searchNrComanda"
              name="searchNrComanda"
              placeholder="Nr. comandă"
              value="{{ $searchNrComanda }}">
          </div>
        </div>
        <div class="row custom-search-form justify-content-center">
          <div class="col-lg-4 mb-2">
            <button
              class="btn btn-sm w-100 btn-primary text-white border border-dark rounded-3"
              type="submit">
              <i class="fas fa-search me-1"></i>Caută
            </button>
          </div>
          <div class="col-lg-4 mb-2">
            <a
              class="btn btn-sm w-100 btn-secondary text-white border border-dark rounded-3"
              href="{{ route('comenzi.iesiri.index') }}">
              <i class="far fa-trash-alt me-1"></i>Resetează
            </a>
          </div>
        </div>
      </form>
    </div>
    <div class="col-lg-3 text-end">
      {{-- Placeholder for future actions --}}
    </div>
  </div>
  <div class="card-body px-0 py-3">
    @include('errors.errors')

    <div class="table-responsive rounded">
      <table class="table table-striped table-hover rounded">
        <thead class="text-white">
          <tr>
            <th class="text-white culoare2">#</th>
            <th class="text-white culoare2">
              <a
                href="{{ $buildSortUrl('number') }}"
                class="text-white text-decoration-none d-inline-flex align-items-center gap-1">
                Nr. comandă
                <i class="fa-solid {{ $sortIcon('number') }}"></i>
              </a>
            </th>
            <th class="text-white culoare2">Prima livrare</th>
            <th class="text-white culoare2">Ultima livrare</th>
            <th class="text-white culoare2 text-end">Acțiuni</th>
          </tr>
        </thead>
        <tbody>
          @forelse($comenzi as $comanda)
          <tr>
            <td>{{ ($comenzi->currentPage() - 1) * $comenzi->perPage() + $loop->iteration }}</td>
            <td>{{ $comanda->nr_comanda }}</td>
            <td>{{ \Carbon\Carbon::parse($comanda->data_inceput)->format('d.m.Y H:i') }}</td>
            <td>{{ \Carbon\Carbon::parse($comanda->data_sfarsit)->format('d.m.Y H:i') }}</td>
            <td class="text-end">
              <a
                href="{{ route('comenzi.iesiri.show', $comanda->nr_comanda) }}"
                class="btn btn-sm bg-success text-white me-1"
                title="Vizualizează">
                <i class="fa-solid fa-eye"></i>
              </a>
              <a
                href="{{ route('comenzi.iesiri.pdf', $comanda->nr_comanda) }}"
                target="_blank"
                class="btn btn-sm bg-primary text-white"
                title="PDF">
                <i class="fa-solid fa-file-pdf"></i>
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-3">
              <i class="fa-solid fa-exclamation-circle me-1"></i>Nu există comenzi.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <nav>
      <ul class="pagination justify-content-center">
        {{ $comenzi->withQueryString()->links() }}
      </ul>
    </nav>
  </div>
</div>
@endsection
