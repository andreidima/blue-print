@extends('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px;">
  @php
    $statusLabels = [
      'auto-draft' => 'Ciornă automată',
      'cancelled' => 'Anulată',
      'completed' => 'Finalizată',
      'draft' => 'Ciornă',
      'failed' => 'Eșuată',
      'on-hold' => 'Fără stoc',
      'pending' => 'În așteptare',
      'processing' => 'În procesare',
      'refunded' => 'Rambursată',
      'trash' => 'Ștearsă',
    ]
  @endphp
  <div class="row card-header align-items-center" style="border-radius:40px 40px 0 0;">
    <div class="col-lg-3">
      <span class="badge culoare1 fs-5">
        <i class="fa-solid fa-store me-1"></i> Comenzi site
      </span>
    </div>
    <div class="col-lg-6">
      @php
        $formAction = $formAction ?? route('woocommerce.orders.index');

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
      <form class="needs-validation" novalidate method="GET" action="{{ $formAction }}">
        <input type="hidden" name="sort" value="{{ request('sort') }}">
        <input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">
        <div class="row mb-2 custom-search-form justify-content-center">
          <div class="col-lg-4 mb-2 mb-lg-0">
            <input
              type="text"
              class="form-control rounded-3"
              id="searchTerm"
              name="searchTerm"
              placeholder="Caută după nr., client, telefon"
              value="{{ $searchTerm }}">
          </div>
          <div class="col-lg-3 mb-2 mb-lg-0">
            <select
              class="form-control rounded-3"
              id="status"
              name="status">
              <option value="">Toate statusurile</option>
              @foreach($statusOptions as $statusOption)
                <option value="{{ $statusOption }}" {{ $status === $statusOption ? 'selected' : '' }}>
                  {{ $statusLabels[$statusOption] ?? ucfirst(str_replace('-', ' ', $statusOption)) }}
                </option>
              @endforeach
            </select>
          </div>
          <div id="datePicker" class="col-lg-5 d-flex align-items-center">
            <label for="searchIntervalData" class="mb-0 ps-3">Data creare</label>
            <vue-datepicker-next
              id="searchIntervalData"
              data-veche="{{ $searchIntervalData }}"
              nume-camp-db="searchIntervalData"
              tip="date"
              range="range"
              value-type="YYYY-MM-DD"
              format="DD.MM.YYYY"
              :latime="{ width: '210px' }"
            ></vue-datepicker-next>
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
              href="{{ $formAction }}">
              <i class="far fa-trash-alt me-1"></i>Resetează
            </a>
          </div>
        </div>
      </form>
    </div>
    <div class="col-lg-3 text-end">
      {{-- Placeholder pentru acțiuni viitoare --}}
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
            <th class="text-white culoare2">
              <a
                href="{{ $buildSortUrl('name') }}"
                class="text-white text-decoration-none d-inline-flex align-items-center gap-1">
                Client
                <i class="fa-solid {{ $sortIcon('name') }}"></i>
              </a>
            </th>
            <th class="text-white culoare2">Status</th>
            <th class="text-white culoare2 text-center">Onorare stoc</th>
            <th class="text-white culoare2 text-end">Total</th>
            <th class="text-white culoare2 text-center">Produse</th>
            <th class="text-white culoare2 text-end">Creată la</th>
          </tr>
        </thead>
        <tbody>
          @forelse($orders as $order)
              @php
                $orderNumber = $order->meta['number'] ?? $order->woocommerce_id;
                $customer = $order->customer;
                $billing = $order->addresses->first();
                $firstName = optional($customer)->first_name ?? optional($billing)->first_name ?? '';
                $lastName = optional($customer)->last_name ?? optional($billing)->last_name ?? '';
                $customerName = trim($firstName . ' ' . $lastName) ?: null;
                $customerEmail = optional($customer)->email ?? optional($billing)->email;
                $badgeClasses = [
                  'completed' => 'bg-success',
                  'processing' => 'bg-warning text-dark',
                  'pending' => 'bg-secondary',
                  'on-hold' => 'bg-danger',
                  'cancelled' => 'bg-danger',
                  'refunded' => 'bg-info text-dark',
                ];
                $statusClass = $badgeClasses[$order->status] ?? 'bg-secondary';
                $statusLabel = $statusLabels[$order->status] ?? ucfirst(str_replace('-', ' ', $order->status));
              @endphp
              <tr>
                <td>{{ ($orders->currentPage() - 1) * $orders->perPage() + $loop->iteration }}</td>
                <td>
                  <span class="fw-semibold">{{ $orderNumber }}</span>
                  <div class="text-muted small">#{{ $order->woocommerce_id }}</div>
                </td>
                <td>
                  <div>{{ $customerName ?? '—' }}</div>
                  @if($customerEmail)
                    <div class="text-muted small">{{ $customerEmail }}</div>
                  @endif
                  @if(optional($billing)->phone)
                    <div class="text-muted small"><i class="fa-solid fa-phone me-1"></i>{{ $billing->phone }}</div>
                  @endif
                </td>
                <td>
                  <span class="badge rounded-pill {{ $statusClass }}">
                    {{ $statusLabel }}
                  </span>
                </td>
                <td class="text-center">
                  <span class="badge {{ $order->fulfillment['badge'] }}">{{ $order->fulfillment['label'] }}</span>
                  @if($order->fulfillment_total_quantity > 0)
                    <div class="text-muted small">{{ $order->fulfillment_fulfilled_quantity }} / {{ $order->fulfillment_total_quantity }} produse</div>
                  @endif
                </td>
                <td class="text-end">
                  {{ number_format((float) $order->total, 2, ',', '.') }} {{ $order->currency ?? '' }}
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark">{{ $order->items_count }}</span>
                </td>
                <td class="text-end">
                  {{ optional($order->date_created)->format('d.m.Y H:i') ?? '—' }}
                </td>
              </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-3">
                <i class="fa-solid fa-exclamation-circle me-1"></i>Nu există comenzi site.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <nav>
      <ul class="d-flex justify-content-center">
          {{ $orders->withQueryString()->links() }}
      </ul>
    </nav>
  </div>
</div>
@endsection
