@extends ('layouts.app')

@section('content')
@php
    $canWriteClienti = auth()->user()?->hasPermission('clienti.write') ?? false;
    $canBulkActionsClienti = $canWriteClienti;
    $currentSort = $sort ?? 'nume';
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
    $emptyColspan = 7;
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-address-book"></i> Clienti
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

        <div class="col-lg-3 d-flex flex-column justify-content-between" style="min-height: 110px;">
            <div class="text-end">
                @if ($canWriteClienti)
                    <a class="btn btn-sm btn-success text-white border border-dark rounded-3" href="{{ route('clienti.create') }}" role="button">
                        <i class="fas fa-user-plus text-white me-1"></i> Adauga client
                    </a>
                @endif
            </div>
            @if ($canBulkActionsClienti)
                <div class="d-flex flex-column align-items-end gap-2 mt-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger border border-dark rounded-3"
                        data-client-bulk-delete
                    >
                        <i class="fa-solid fa-trash me-1"></i> Sterge selectate
                    </button>
                    <a
                        class="btn btn-sm btn-outline-secondary border border-dark rounded-3"
                        href="{{ route('clienti.trash') }}"
                    >
                        <i class="fa-solid fa-trash-can-arrow-up me-1"></i> Vezi trash
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="card-body px-0 py-3">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Clienti table">
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
                        <th scope="col" class="text-white culoare2 text-nowrap" width="20%">
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
                        <th scope="col" class="text-white culoare2 text-nowrap" width="18%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'telefon', 'dir' => $sortDirFor('telefon')]) }}">
                                <i class="fa-solid fa-phone me-1"></i> Telefon
                                <i class="fa-solid {{ $sortIcon('telefon') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="20%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'email', 'dir' => $sortDirFor('email')]) }}">
                                <i class="fa-solid fa-envelope me-1"></i> Emailuri
                                <i class="fa-solid {{ $sortIcon('email') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="14%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'dir' => $sortDirFor('created_at')]) }}">
                                <i class="fa-solid fa-calendar-days me-1"></i> Data adaugarii
                                <i class="fa-solid {{ $sortIcon('created_at') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-end" width="13%"><i class="fa-solid fa-cogs me-1"></i> Actiuni</th>
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
                            <td>
                                {{ $client->nume_complet }}
                            </td>
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
                            <td>
                                {{ $client->email ?: '-' }}
                            </td>
                            <td>
                                {{ optional($client->created_at)->format('d.m.Y H:i') }}
                            </td>
                            <td>
                                <div class="d-flex justify-content-end py-0">
                                    <a href="{{ route('clienti.show', $client) }}" class="flex me-1" aria-label="Vezi {{ $client->nume_complet }}">
                                        <span class="badge bg-success"><i class="fa-solid fa-eye"></i></span>
                                    </a>
                                    <a href="{{ route('clienti.edit', $client) }}" class="flex me-1" aria-label="Modifica {{ $client->nume_complet }}">
                                        <span class="badge bg-primary"><i class="fa-solid fa-edit"></i></span>
                                    </a>
                                    @if ($canWriteClienti)
                                        <form method="POST" action="{{ route('clienti.destroy', $client) }}" data-confirm="Sigur vrei sa stergi acest client? Va fi mutat in trash.">
                                            @method('DELETE')
                                            @csrf
                                            <button type="submit" class="badge bg-danger border-0" aria-label="Sterge {{ $client->nume_complet }}">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $emptyColspan }}" class="text-center text-muted py-5">
                                <i class="fa-solid fa-user-slash fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu s-au gasit clienti in baza de date.</p>
                                @if($search || $searchNume || $searchTelefon || $searchEmail || $type)
                                    <p class="small mb-0 mt-2">Incearca sa modifici criteriile de cautare.</p>
                                @endif
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
    <form id="clienti-bulk-delete-form" method="POST" action="{{ route('clienti.bulk-destroy') }}" class="d-none">
        @csrf
        @method('DELETE')
        <div data-client-bulk-inputs></div>
    </form>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const selectAll = document.querySelector('[data-client-select-all]');
            const itemCheckboxes = Array.from(document.querySelectorAll('[data-client-select]'));
            const bulkDeleteBtn = document.querySelector('[data-client-bulk-delete]');
            const bulkDeleteForm = document.getElementById('clienti-bulk-delete-form');
            const bulkInputsWrap = document.querySelector('[data-client-bulk-inputs]');

            if (!bulkDeleteBtn || !bulkDeleteForm || !bulkInputsWrap || itemCheckboxes.length === 0) {
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
                bulkDeleteBtn.disabled = selectedCount === 0;
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

            bulkDeleteBtn.addEventListener('click', async () => {
                const selected = selectedValues();
                if (selected.length === 0) {
                    return;
                }

                const confirmMessage = selected.length === 1
                    ? 'Esti de acord sa stergi clientul selectat? Va fi mutat in trash.'
                    : 'Esti de acord sa stergi clientii selectati? Acestia vor fi mutati in trash.';

                const confirmed = await confirmWithModal({
                    title: 'Confirmare stergere',
                    message: confirmMessage,
                    confirmText: 'Sterge',
                    confirmClass: 'btn-danger',
                });
                if (!confirmed) {
                    return;
                }

                bulkInputsWrap.innerHTML = '';
                selected.forEach((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'client_ids[]';
                    input.value = id;
                    bulkInputsWrap.appendChild(input);
                });

                bulkDeleteForm.submit();
            });

            syncSelectAllState();
        });
    </script>
@endif
@endsection
