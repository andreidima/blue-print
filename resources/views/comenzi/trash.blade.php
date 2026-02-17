@extends ('layouts.app')

@section('content')
@php
    $title = $pageTitle ?? 'Comenzi - Trash';
    $statusPlataOptions = \App\Enums\StatusPlata::options();
    $currentUser = auth()->user();
    $canWriteComenzi = $currentUser?->hasPermission('comenzi.write') ?? false;
    $canBulkActionsComenzi = $canWriteComenzi;
    $currentSort = $sort ?? null;
    $currentDir = $dir ?? 'asc';
    $sortIcon = function (string $column) use ($currentSort, $currentDir) {
        if ($currentSort === $column) {
            return $currentDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        }

        return 'fa-sort';
    };
    $sortDirFor = function (string $column) use ($currentSort, $currentDir) {
        if ($currentSort !== $column) {
            return 'asc';
        }

        return $currentDir === 'asc' ? 'desc' : 'asc';
    };
    $emptyColspan = 11;
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-2">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-trash-can"></i> {{ $title }}
            </span>
        </div>

        <div class="col-lg-7">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                @if ($fixedTip)
                    <input type="hidden" name="tip" value="{{ $fixedTip }}">
                @endif
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-3 mb-1">
                        <input type="text" class="form-control rounded-3" id="client" name="client" placeholder="Client: nume/telefon/email" value="{{ $client }}">
                    </div>
                    <div class="col-lg-3 mb-1">
                        <select class="form-select rounded-3" name="status">
                            <option value="">Status</option>
                            @foreach ($statusuri as $key => $label)
                                <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 mb-1">
                        <select class="form-select rounded-3" name="sursa">
                            <option value="">Sursa</option>
                            @foreach ($surse as $key => $label)
                                <option value="{{ $key }}" {{ $sursa === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 mb-1">
                        <input type="date" class="form-control rounded-3" id="timp_de" name="timp_de" value="{{ $dataDe }}">
                    </div>
                    <div class="col-lg-3 mb-1">
                        <input type="date" class="form-control rounded-3" id="timp_pana" name="timp_pana" value="{{ $dataPana }}">
                    </div>
                    <div class="col-lg-4">
                        <button class="btn btn-sm w-100 btn-primary text-white border border-dark rounded-3" type="submit">
                            <i class="fas fa-search text-white me-1"></i>Cauta
                        </button>
                    </div>
                    <div class="col-lg-4">
                        <a class="btn btn-sm w-100 btn-secondary text-white border border-dark rounded-3" href="{{ url()->current() }}" role="button">
                            <i class="far fa-trash-alt text-white me-1"></i>Reseteaza cautarea
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-3 d-flex flex-column justify-content-between" style="min-height: 130px;">
            <div class="text-end">
                <a class="btn btn-sm btn-outline-secondary border border-dark rounded-3" href="{{ $activeRoute }}">
                    <i class="fa-solid fa-arrow-left me-1"></i> Inapoi la lista
                </a>
            </div>
            @if ($canBulkActionsComenzi)
                <div class="d-flex flex-column align-items-end gap-2 mt-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary border border-dark rounded-3"
                        data-comanda-bulk-restore
                    >
                        <i class="fa-solid fa-rotate-left me-1"></i> Restaureaza selectate
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger border border-dark rounded-3"
                        data-comanda-bulk-force-delete
                    >
                        <i class="fa-solid fa-trash me-1"></i> Sterge definitiv selectate
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="card-body px-0 py-3">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Comenzi trash table">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2 text-nowrap" width="5%">
                            <div class="d-flex align-items-center gap-2">
                                @if ($canBulkActionsComenzi)
                                    <input type="checkbox" class="form-check-input" data-comanda-select-all aria-label="Selecteaza toate comenzile">
                                @endif
                                <i class="fa-solid fa-hashtag"></i>
                            </div>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="20%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'client', 'dir' => $sortDirFor('client')]) }}">
                                <i class="fa-solid fa-user me-1"></i> Client
                                <i class="fa-solid {{ $sortIcon('client') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'solicitare', 'dir' => $sortDirFor('solicitare')]) }}">
                                <i class="fa-solid fa-calendar-day me-1"></i> Solicitare
                                <i class="fa-solid {{ $sortIcon('solicitare') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'tip', 'dir' => $sortDirFor('tip')]) }}">
                                <i class="fa-solid fa-tag me-1"></i> Tip
                                <i class="fa-solid {{ $sortIcon('tip') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="13%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'status', 'dir' => $sortDirFor('status')]) }}">
                                <i class="fa-solid fa-list-check me-1"></i> Status
                                <i class="fa-solid {{ $sortIcon('status') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'sursa', 'dir' => $sortDirFor('sursa')]) }}">
                                <i class="fa-solid fa-circle-up me-1"></i> Sursa
                                <i class="fa-solid {{ $sortIcon('sursa') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="12%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'livrare', 'dir' => $sortDirFor('livrare')]) }}">
                                <i class="fa-solid fa-clock me-1"></i> Livrare
                                <i class="fa-solid {{ $sortIcon('livrare') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="8%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'total', 'dir' => $sortDirFor('total')]) }}">
                                <i class="fa-solid fa-money-bill me-1"></i> Total
                                <i class="fa-solid {{ $sortIcon('total') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="8%">
                            <i class="fa-solid fa-credit-card me-1"></i> Plata
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'deleted_at', 'dir' => $sortDirFor('deleted_at')]) }}">
                                <i class="fa-solid fa-trash me-1"></i> Sters la
                                <i class="fa-solid {{ $sortIcon('deleted_at') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap text-end" width="10%"><i class="fa-solid fa-cogs me-1"></i> Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($comenzi as $comanda)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($canBulkActionsComenzi)
                                        <input type="checkbox" class="form-check-input" value="{{ $comanda->id }}" data-comanda-select aria-label="Selecteaza comanda {{ $comanda->id }}">
                                    @endif
                                    <span>{{ ($comenzi->currentPage() - 1) * $comenzi->perPage() + $loop->index + 1 }}</span>
                                </div>
                            </td>
                            <td>
                                <div>{{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                                <div class="small text-muted">{{ optional($comanda->client)->telefon }}</div>
                            </td>
                            <td>{{ optional($comanda->data_solicitarii)->format('d.m.Y') }}</td>
                            <td>{{ $tipuri[$comanda->tip] ?? $comanda->tip }}</td>
                            <td>{{ $statusuri[$comanda->status] ?? $comanda->status }}</td>
                            <td>{{ $surse[$comanda->sursa] ?? $comanda->sursa }}</td>
                            <td>{{ optional($comanda->timp_estimat_livrare)->format('d.m.Y H:i') }}</td>
                            <td>{{ number_format($comanda->total, 2) }}</td>
                            <td>{{ $statusPlataOptions[$comanda->status_plata] ?? $comanda->status_plata }}</td>
                            <td>{{ optional($comanda->deleted_at)->format('d.m.Y H:i') }}</td>
                            <td>
                                @if ($canWriteComenzi)
                                    <div class="d-flex justify-content-end py-0 gap-1">
                                        <form method="POST" action="{{ route('comenzi.restore', $comanda->id) }}" data-confirm="Sigur vrei sa restaurezi aceasta comanda din trash?">
                                            @method('PATCH')
                                            @csrf
                                            <button type="submit" class="badge bg-success border-0" aria-label="Restaureaza comanda {{ $comanda->id }}">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('comenzi.force-delete', $comanda->id) }}" data-confirm="Sigur vrei sa stergi definitiv aceasta comanda?">
                                            @method('DELETE')
                                            @csrf
                                            <button type="submit" class="badge bg-danger border-0" aria-label="Sterge definitiv comanda {{ $comanda->id }}">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $emptyColspan }}" class="text-center text-muted py-5">
                                <i class="fa-solid fa-trash-can fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu exista comenzi in trash.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav>
            <ul class="pagination justify-content-center">
                {{ $comenzi->appends(Request::except('page'))->links() }}
            </ul>
        </nav>

    </div>
</div>

@if ($canBulkActionsComenzi)
    <form id="comenzi-bulk-restore-form" method="POST" action="{{ route('comenzi.bulk-restore') }}" class="d-none">
        @csrf
        @method('PATCH')
        <div data-comanda-bulk-restore-inputs></div>
    </form>

    <form id="comenzi-bulk-force-delete-form" method="POST" action="{{ route('comenzi.bulk-force-delete') }}" class="d-none">
        @csrf
        @method('DELETE')
        <div data-comanda-bulk-force-delete-inputs></div>
    </form>

    <script>
        const selectAllComenzi = document.querySelector('[data-comanda-select-all]');
        const comandaCheckboxes = Array.from(document.querySelectorAll('[data-comanda-select]'));
        const comandaBulkRestoreBtn = document.querySelector('[data-comanda-bulk-restore]');
        const comandaBulkForceDeleteBtn = document.querySelector('[data-comanda-bulk-force-delete]');
        const comandaBulkRestoreForm = document.getElementById('comenzi-bulk-restore-form');
        const comandaBulkForceDeleteForm = document.getElementById('comenzi-bulk-force-delete-form');
        const comandaBulkRestoreInputsWrap = document.querySelector('[data-comanda-bulk-restore-inputs]');
        const comandaBulkForceDeleteInputsWrap = document.querySelector('[data-comanda-bulk-force-delete-inputs]');
        const confirmWithModal = (options) => window.AppConfirm.confirm(options);

        const selectedComandaIds = () => comandaCheckboxes.filter((cb) => cb.checked).map((cb) => cb.value);

        const syncComandaSelectState = () => {
            if (
                !comandaBulkRestoreBtn
                || !comandaBulkForceDeleteBtn
                || comandaCheckboxes.length === 0
            ) {
                return;
            }

            const selectedCount = selectedComandaIds().length;
            comandaBulkRestoreBtn.disabled = selectedCount === 0;
            comandaBulkForceDeleteBtn.disabled = selectedCount === 0;

            if (selectAllComenzi) {
                selectAllComenzi.checked = selectedCount === comandaCheckboxes.length;
                selectAllComenzi.indeterminate = selectedCount > 0 && selectedCount < comandaCheckboxes.length;
            }
        };

        const fillBulkInputs = (wrap, ids) => {
            wrap.innerHTML = '';
            ids.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'comanda_ids[]';
                input.value = id;
                wrap.appendChild(input);
            });
        };

        if (selectAllComenzi) {
            selectAllComenzi.addEventListener('change', () => {
                comandaCheckboxes.forEach((cb) => {
                    cb.checked = selectAllComenzi.checked;
                });
                syncComandaSelectState();
            });
        }

        comandaCheckboxes.forEach((cb) => cb.addEventListener('change', syncComandaSelectState));

        if (
            comandaBulkRestoreBtn
            && comandaBulkRestoreForm
            && comandaBulkRestoreInputsWrap
        ) {
            comandaBulkRestoreBtn.addEventListener('click', async () => {
                const selected = selectedComandaIds();
                if (selected.length === 0) {
                    return;
                }

                const confirmed = await confirmWithModal({
                    title: 'Confirmare restaurare',
                    message: selected.length === 1
                        ? 'Esti de acord sa restaurezi comanda selectata?'
                        : 'Esti de acord sa restaurezi comenzile selectate?',
                    confirmText: 'Restaureaza',
                    confirmClass: 'btn-primary',
                });
                if (!confirmed) {
                    return;
                }

                fillBulkInputs(comandaBulkRestoreInputsWrap, selected);
                comandaBulkRestoreForm.submit();
            });
        }

        if (
            comandaBulkForceDeleteBtn
            && comandaBulkForceDeleteForm
            && comandaBulkForceDeleteInputsWrap
        ) {
            comandaBulkForceDeleteBtn.addEventListener('click', async () => {
                const selected = selectedComandaIds();
                if (selected.length === 0) {
                    return;
                }

                const confirmed = await confirmWithModal({
                    title: 'Confirmare stergere definitiva',
                    message: selected.length === 1
                        ? 'Esti de acord sa stergi definitiv comanda selectata?'
                        : 'Esti de acord sa stergi definitiv comenzile selectate?',
                    confirmText: 'Sterge definitiv',
                    confirmClass: 'btn-danger',
                });
                if (!confirmed) {
                    return;
                }

                fillBulkInputs(comandaBulkForceDeleteInputsWrap, selected);
                comandaBulkForceDeleteForm.submit();
            });
        }

        syncComandaSelectState();
    </script>
@endif
@endsection
