@extends('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px;">
  <div class="row card-header align-items-center" style="border-radius:40px 40px 0 0;">
    <div class="col-lg-3">
      <span class="badge culoare1 fs-5">
        <i class="fa-solid fa-store me-1"></i> Comenzi site
      </span>
    </div>
    <div class="col-lg-6">
      @php($formAction = $formAction ?? route('woocommerce.orders.index'))
      <form class="needs-validation" novalidate method="GET" action="{{ $formAction }}">
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
                  {{ ucfirst(str_replace('-', ' ', $statusOption)) }}
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
            <th class="text-white culoare2">Nr. comandă</th>
            <th class="text-white culoare2">Client</th>
            <th class="text-white culoare2">Status</th>
            <th class="text-white culoare2 text-end">Total</th>
            <th class="text-white culoare2 text-center">Produse</th>
            <th class="text-white culoare2">Creată la</th>
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
                'cancelled' => 'bg-danger',
                'refunded' => 'bg-info text-dark',
              ];
              $statusClass = $badgeClasses[$order->status] ?? 'bg-secondary';
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
                  {{ ucfirst(str_replace('-', ' ', $order->status)) }}
                </span>
              </td>
              <td class="text-end">
                {{ number_format((float) $order->total, 2, ',', '.') }} {{ $order->currency ?? '' }}
              </td>
              <td class="text-center">
                <span class="badge bg-light text-dark">{{ $order->items_count }}</span>
              </td>
              <td>
                {{ optional($order->date_created)->format('d.m.Y H:i') ?? '—' }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-3">
                <i class="fa-solid fa-exclamation-circle me-1"></i>Nu există comenzi site.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <nav>
      <ul class="pagination justify-content-center">
        {{ $orders->withQueryString()->links() }}
      </ul>
    </nav>
  </div>
</div>
@endsection
