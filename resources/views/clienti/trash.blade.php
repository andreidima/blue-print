@extends ('layouts.app')

@section('content')
@php
    $canWriteClienti = auth()->user()?->hasPermission('clienti.write') ?? false;
    $canBulkActionsClienti = $canWriteClienti;
    $currentSort = $sort ?? 'deleted_at';
    $currentDir = $dir ?? 'desc';
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
    $emptyColspan = 8;
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-trash-can"></i> Clienti - Trash
            </span>
        </div>

        <div class="col-lg-6">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-4 mb-1">
                        <input type="text" class="form-control rounded-3" name="searchNume" placeholder="Nume" value="{{ $searchNume }}">
                    </div>
                    <div class="col-lg-4 mb-1">
                        <input type="text" class="form-control rounded-3" name="searchTelefon" placeholder="Telefon" value="{{ $searchTelefon }}">
                    </div>
                    <div class="col-lg-4 mb-1">
                        <input type="text" class="form-control rounded-3" name="searchEmail" placeholder="Email" value="{{ $searchEmail }}">
                    </div>
                    <div class="col-lg-6 mb-1">
                        <select class="form-select rounded-3" name="type">
                            <option value="">Tip client</option>
                            <option value="pf" {{ (string) $type === 'pf' ? 'selected' : '' }}>PF</option>
                            <option value="pj" {{ (string) $type === 'pj' ? 'selected' : '' }}>PJ</option>
                        </select>
                    </div>
                    <div class="col-lg-6 mb-1"></div>
                </div>
                <div class="row custom-search-form justify-content-center">
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
                <a class="btn btn-sm btn-outline-secondary border border-dark rounded-3" href="{{ route('clienti.index') }}">
                    <i class="fa-solid fa-arrow-left me-1"></i> Inapoi la clienti
                </a>
            </div>
            @if ($canBulkActionsClienti)
                <div class="d-flex flex-column align-items-end gap-2 mt-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary border border-dark rounded-3"
                        data-client-bulk-restore
                    >
                        <i class="fa-solid fa-rotate-left me-1"></i> Restaureaza selectate
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger border border-dark rounded-3"
                        data-client-bulk-force-delete
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
            <table class="table table-striped table-hover rounded" aria-label="Clienti trash table">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2 text-nowrap" width="7%">
                            <div class="d-flex align-items-center gap-2">
                                @if ($canBulkActionsClienti)
                                    <input type="checkbox" class="form-check-input" data-client-select-all aria-label="Selecteaza toti clientii">
                                @endif
                                <i class="fa-solid fa-hashtag"></i>
                            </div>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="18%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'nume', 'dir' => $sortDirFor('nume')]) }}">
                                <i class="fa-solid fa-user me-1"></i> Client
                                <i class="fa-solid {{ $sortIcon('nume') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="8%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'type', 'dir' => $sortDirFor('type')]) }}">
                                <i class="fa-solid fa-id-badge me-1"></i> Tip
                                <i class="fa-solid {{ $sortIcon('type') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="16%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'telefon', 'dir' => $sortDirFor('telefon')]) }}">
                                <i class="fa-solid fa-phone me-1"></i> Telefon
                                <i class="fa-solid {{ $sortIcon('telefon') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="18%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'email', 'dir' => $sortDirFor('email')]) }}">
                                <i class="fa-solid fa-envelope me-1"></i> Emailuri
                                <i class="fa-solid {{ $sortIcon('email') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="13%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'dir' => $sortDirFor('created_at')]) }}">
                                <i class="fa-solid fa-calendar-days me-1"></i> Data adaugarii
                                <i class="fa-solid {{ $sortIcon('created_at') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="13%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'deleted_at', 'dir' => $sortDirFor('deleted_at')]) }}">
                                <i class="fa-solid fa-trash me-1"></i> Sters la
                                <i class="fa-solid {{ $sortIcon('deleted_at') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-end" width="7%"><i class="fa-solid fa-cogs me-1"></i> Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clienti as $client)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($canBulkActionsClienti)
                                        <input type="checkbox" class="form-check-input" value="{{ $client->id }}" data-client-select aria-label="Selecteaza clientul {{ $client->nume_complet }}">
                                    @endif
                                    <span>{{ ($clienti->currentPage() - 1) * $clienti->perPage() + $loop->index + 1 }}</span>
                                </div>
                            </td>
                            <td>{{ $client->nume_complet }}</td>
                            <td>
                                <span class="badge {{ $client->type === 'pj' ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ strtoupper($client->type ?? 'pf') }}
                                </span>
                            </td>
                            <td>
                                <div>{{ $client->telefon }}</div>
                                @if ($client->telefon_secundar)
                                    <div class="small text-muted">{{ $client->telefon_secundar }}</div>
                                @endif
                            </td>
                            <td>{{ $client->email ?: '-' }}</td>
                            <td>{{ optional($client->created_at)->format('d.m.Y H:i') }}</td>
                            <td>{{ optional($client->deleted_at)->format('d.m.Y H:i') }}</td>
                            <td>
                                @if ($canWriteClienti)
                                    <div class="d-flex justify-content-end py-0 gap-1">
                                        <form method="POST" action="{{ route('clienti.restore', $client->id) }}" data-confirm="Sigur vrei sa restaurezi acest client din trash?">
                                            @method('PATCH')
                                            @csrf
                                            <button type="submit" class="badge bg-success border-0" aria-label="Restaureaza {{ $client->nume_complet }}">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('clienti.force-delete', $client->id) }}" data-confirm="Sigur vrei sa stergi definitiv acest client?">
                                            @method('DELETE')
                                            @csrf
                                            <button type="submit" class="badge bg-danger border-0" aria-label="Sterge definitiv {{ $client->nume_complet }}">
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
                                <p class="mb-0">Nu exista clienti in trash.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($clienti->total() > 0)
            <div class="d-flex flex-wrap justify-content-between align-items-center px-3 mt-2 mb-3 small text-muted">
                <span>Afisare {{ $clienti->firstItem() }}-{{ $clienti->lastItem() }} din {{ $clienti->total() }} clienti</span>
                <span>Pagina {{ $clienti->currentPage() }} din {{ $clienti->lastPage() }}</span>
            </div>
        @endif

        <nav>
            <ul class="pagination justify-content-center">
                {{ $clienti->appends(Request::except('page'))->links() }}
            </ul>
        </nav>

    </div>
</div>

@if ($canBulkActionsClienti)
    <form id="clienti-bulk-restore-form" method="POST" action="{{ route('clienti.bulk-restore') }}" class="d-none">
        @csrf
        @method('PATCH')
        <div data-client-bulk-restore-inputs></div>
    </form>

    <form id="clienti-bulk-force-delete-form" method="POST" action="{{ route('clienti.bulk-force-delete') }}" class="d-none">
        @csrf
        @method('DELETE')
        <div data-client-bulk-force-delete-inputs></div>
    </form>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const selectAll = document.querySelector('[data-client-select-all]');
            const itemCheckboxes = Array.from(document.querySelectorAll('[data-client-select]'));
            const bulkRestoreBtn = document.querySelector('[data-client-bulk-restore]');
            const bulkForceDeleteBtn = document.querySelector('[data-client-bulk-force-delete]');
            const bulkRestoreForm = document.getElementById('clienti-bulk-restore-form');
            const bulkForceDeleteForm = document.getElementById('clienti-bulk-force-delete-form');
            const bulkRestoreInputsWrap = document.querySelector('[data-client-bulk-restore-inputs]');
            const bulkForceDeleteInputsWrap = document.querySelector('[data-client-bulk-force-delete-inputs]');

            if (
                itemCheckboxes.length === 0
                || !bulkRestoreBtn
                || !bulkForceDeleteBtn
                || !bulkRestoreForm
                || !bulkForceDeleteForm
                || !bulkRestoreInputsWrap
                || !bulkForceDeleteInputsWrap
            ) {
                return;
            }

            const confirmWithModal = (options) => window.AppConfirm.confirm(options);

            const selectedValues = () => itemCheckboxes.filter((cb) => cb.checked).map((cb) => cb.value);

            const syncSelectAllState = () => {
                const selectedCount = selectedValues().length;
                if (selectAll) {
                    selectAll.checked = selectedCount === itemCheckboxes.length;
                    selectAll.indeterminate = selectedCount > 0 && selectedCount < itemCheckboxes.length;
                }
                bulkRestoreBtn.disabled = selectedCount === 0;
                bulkForceDeleteBtn.disabled = selectedCount === 0;
            };

            const fillInputs = (target, ids) => {
                target.innerHTML = '';
                ids.forEach((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'client_ids[]';
                    input.value = id;
                    target.appendChild(input);
                });
            };

            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    itemCheckboxes.forEach((cb) => {
                        cb.checked = selectAll.checked;
                    });
                    syncSelectAllState();
                });
            }

            itemCheckboxes.forEach((cb) => cb.addEventListener('change', syncSelectAllState));

            bulkRestoreBtn.addEventListener('click', async () => {
                const selected = selectedValues();
                if (selected.length === 0) {
                    return;
                }

                const confirmed = await confirmWithModal({
                    title: 'Confirmare restaurare',
                    message: selected.length === 1
                        ? 'Esti de acord sa restaurezi clientul selectat?'
                        : 'Esti de acord sa restaurezi clientii selectati?',
                    confirmText: 'Restaureaza',
                    confirmClass: 'btn-primary',
                });
                if (!confirmed) {
                    return;
                }

                fillInputs(bulkRestoreInputsWrap, selected);
                bulkRestoreForm.submit();
            });

            bulkForceDeleteBtn.addEventListener('click', async () => {
                const selected = selectedValues();
                if (selected.length === 0) {
                    return;
                }

                const confirmed = await confirmWithModal({
                    title: 'Confirmare stergere definitiva',
                    message: selected.length === 1
                        ? 'Esti de acord sa stergi definitiv clientul selectat?'
                        : 'Esti de acord sa stergi definitiv clientii selectati?',
                    confirmText: 'Sterge definitiv',
                    confirmClass: 'btn-danger',
                });
                if (!confirmed) {
                    return;
                }

                fillInputs(bulkForceDeleteInputsWrap, selected);
                bulkForceDeleteForm.submit();
            });

            syncSelectAllState();
        });
    </script>
@endif
@endsection
