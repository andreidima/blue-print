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
      'pending' => 'În așteptare (Plată)',
      'on-hold' => 'În așteptare',
      'processing' => 'În procesare',
      'refunded' => 'Rambursată',
      'trash' => 'Ștearsă',
    ];

    $normalizeStatus = fn (string $status): string => str_replace(['wc-', '_', ' '], ['', '-', '-'], strtolower($status));

    $statusLabelFor = function (string $status) use ($statusLabels, $normalizeStatus) {
        $normalized = $normalizeStatus($status);

        return $statusLabels[$normalized]
          ?? \Illuminate\Support\Str::of($normalized)->replace('-', ' ')->title();
    };
    $canSyncOrders = auth()->check();
    $canManageOrderStatus = auth()->user()?->can('admin-action') ?? false;

    $statusSelectOptions = collect($statusOptions ?? [])
        ->mapWithKeys(fn ($option) => [$option => $statusLabelFor($option)])
        ->all();
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
                  {{ $statusLabelFor($statusOption) }}
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
      <div class="text-muted small mb-2">
        <i class="fa-regular fa-clock me-1"></i>
        Ultima sincronizare:
        @if($lastSyncedAt)
          <span title="{{ $lastSyncedAt->format('d.m.Y H:i:s') }}">
            {{ $lastSyncedAt->diffForHumans() }}
          </span>
        @else
          <span>—</span>
        @endif
      </div>
      @if($canSyncOrders)
        <form
          method="POST"
          action="{{ route('woocommerce.orders.sync') }}"
          onsubmit="return confirm('Sigur dorești să sincronizezi manual comenzile WooCommerce?');"
          class="d-inline">
          @csrf
          <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">
            <i class="fa-solid fa-rotate"></i>
            Sincronizează acum
          </button>
        </form>
      @endif
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
                $normalizedStatus = $normalizeStatus($order->status);
                $statusClass = $badgeClasses[$normalizedStatus] ?? 'bg-secondary';
                $statusLabel = $statusLabelFor($order->status);
                $statusOptionsForOrder = $statusSelectOptions;
                $statusOptionsForOrder[$order->status] = $statusLabel;
                asort($statusOptionsForOrder, SORT_NATURAL | SORT_FLAG_CASE);
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
                  @if($canManageOrderStatus)
                    <form
                      method="POST"
                      action="{{ route('woocommerce.orders.status-change', $order) }}"
                      class="js-order-status-form"
                      data-confirm-message="Sigur dorești să setezi comanda {{ $orderNumber }} la „:status”?">
                      @csrf
                      @method('PATCH')
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span
                          class="badge rounded-pill {{ $statusClass }} js-order-status-badge"
                          data-default-class="{{ $statusClass }}"
                          title="{{ $statusLabel }}">
                          {{ $statusLabel }}
                        </span>
                        <select
                          class="form-select form-select-sm js-order-status-select"
                          id="order-status-{{ $order->id }}"
                          name="status"
                          data-current-status="{{ $order->status }}"
                          aria-label="Alege status pentru comanda {{ $orderNumber }}">
                          @foreach($statusOptionsForOrder as $statusValue => $statusOptionLabel)
                            @php
                              $normalizedOption = $normalizeStatus($statusValue);
                              $optionBadgeClass = $badgeClasses[$normalizedOption] ?? 'bg-secondary';
                            @endphp
                            <option
                              value="{{ $statusValue }}"
                              data-badge-class="{{ $optionBadgeClass }}"
                              {{ $statusValue === $order->status ? 'selected' : '' }}>
                              {{ $statusOptionLabel }}
                            </option>
                          @endforeach
                        </select>
                        <div class="spinner-border spinner-border-sm text-primary d-none js-order-status-spinner" role="status">
                          <span class="visually-hidden">Se actualizează...</span>
                        </div>
                      </div>
                    </form>
                  @else
                    <span class="badge rounded-pill {{ $statusClass }}" title="{{ $statusLabel }}">
                      {{ $statusLabel }}
                    </span>
                  @endif
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
@if($canManageOrderStatus)
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('.js-order-status-form').forEach(function (form) {
        var select = form.querySelector('.js-order-status-select');
        if (!select) {
          return;
        }

        var badge = form.querySelector('.js-order-status-badge');
        var spinner = form.querySelector('.js-order-status-spinner');
        var confirmTemplate = form.dataset.confirmMessage || '';

        select.addEventListener('change', function () {
          var selectedOption = select.options[select.selectedIndex];
          if (!selectedOption) {
            return;
          }

          if (confirmTemplate) {
            var message = confirmTemplate.replace(':status', selectedOption.textContent.trim());
            if (!window.confirm(message)) {
              select.value = select.dataset.currentStatus;
              return;
            }
          }

          if (badge) {
            var newClass = selectedOption.dataset.badgeClass || badge.dataset.defaultClass || 'bg-secondary';
            badge.textContent = selectedOption.textContent.trim();
            badge.className = 'badge rounded-pill ' + newClass;
            badge.title = selectedOption.textContent.trim();
          }

          if (spinner) {
            spinner.classList.remove('d-none');
          }

          var hiddenStatusInput = form.querySelector('input[name="status"][type="hidden"]');

          if (!hiddenStatusInput) {
            hiddenStatusInput = document.createElement('input');
            hiddenStatusInput.type = 'hidden';
            hiddenStatusInput.name = 'status';
            form.appendChild(hiddenStatusInput);
          }

          hiddenStatusInput.value = select.value;

          select.dataset.currentStatus = select.value;
          select.setAttribute('disabled', 'disabled');

          form.submit();
        });
      });
    });
  </script>
@endif
@endsection
