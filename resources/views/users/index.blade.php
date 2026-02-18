@extends ('layouts.app')

@section('content')
@php
    $currentSort = $sort ?? 'name';
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
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-users"></i> Utilizatori
            </span>
        </div>

        <div class="col-lg-6">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-4 mb-1">
                        <input type="text" class="form-control rounded-3" id="searchNume" name="searchNume" placeholder="Nume" value="{{ $searchNume }}">
                    </div>
                    <div class="col-lg-4 mb-1">
                        <input type="text" class="form-control rounded-3" id="searchTelefon" name="searchTelefon" placeholder="Telefon" value="{{ $searchTelefon }}">
                    </div>
                    <div class="col-lg-4 mb-1">
                        <input type="text" class="form-control rounded-3" id="searchEmail" name="searchEmail" placeholder="Email" value="{{ $searchEmail }}">
                    </div>
                    <div class="col-lg-6 mb-1">
                        <select class="form-select rounded-3" name="role_id">
                            <option value="">Rol</option>
                            @foreach ($rolesForFilter as $roleOption)
                                <option value="{{ $roleOption->id }}" {{ (string) $roleId === (string) $roleOption->id ? 'selected' : '' }}>
                                    {{ $roleOption->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6 mb-1">
                        <select class="form-select rounded-3" name="activ">
                            <option value="">Stare cont</option>
                            <option value="1" {{ (string) $activ === '1' ? 'selected' : '' }}>Deschis</option>
                            <option value="0" {{ (string) $activ === '0' ? 'selected' : '' }}>Inchis</option>
                        </select>
                    </div>
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

        <div class="col-lg-3 text-end">
            <a class="btn btn-sm btn-success text-white border border-dark rounded-3 col-md-8" href="{{ url()->current() }}/adauga" role="button">
                <i class="fas fa-user-plus text-white me-1"></i> Adauga utilizator
            </a>
        </div>
    </div>

    <div class="card-body px-0 py-3">

        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Users table">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2" width="5%"><i class="fa-solid fa-hashtag"></i></th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="25%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'dir' => $sortDirFor('name')]) }}">
                                <i class="fa-solid fa-user me-1"></i> Nume
                                <i class="fa-solid {{ $sortIcon('name') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="15%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'telefon', 'dir' => $sortDirFor('telefon')]) }}">
                                <i class="fa-solid fa-phone me-1"></i> Telefon
                                <i class="fa-solid {{ $sortIcon('telefon') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="25%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'email', 'dir' => $sortDirFor('email')]) }}">
                                <i class="fa-solid fa-envelope me-1"></i> Email
                                <i class="fa-solid {{ $sortIcon('email') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'role', 'dir' => $sortDirFor('role')]) }}">
                                <i class="fa-solid fa-user-tag me-1"></i> Roluri
                                <i class="fa-solid {{ $sortIcon('role') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'activ', 'dir' => $sortDirFor('activ')]) }}">
                                <i class="fa-solid fa-toggle-on me-1"></i> Stare cont
                                <i class="fa-solid {{ $sortIcon('activ') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-end" width="10%"><i class="fa-solid fa-cogs me-1"></i> Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                {{ ($users->currentpage() - 1) * $users->perpage() + $loop->index + 1 }}
                            </td>
                            <td>
                                {{ $user->name }}
                            </td>
                            <td>
                                {{ $user->telefon }}
                            </td>
                            <td>
                                {{ $user->email }}
                            </td>
                            <td>
                                @php
                                    $visibleRoles = $user->roles->where('slug', '!=', 'superadmin');
                                    $todayDate = now((string) config('app.timezone', 'UTC'))->toDateString();
                                @endphp
                                @if ($visibleRoles->isEmpty())
                                    <span class="text-muted">-</span>
                                @else
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($visibleRoles as $role)
                                            @php
                                                $startDate = $role->pivot?->starts_at ? \Illuminate\Support\Carbon::parse($role->pivot->starts_at)->toDateString() : null;
                                                $endDate = $role->pivot?->ends_at ? \Illuminate\Support\Carbon::parse($role->pivot->ends_at)->toDateString() : null;
                                                $status = 'Activ';
                                                $statusClass = 'bg-success';
                                                if ($startDate !== null && $startDate > $todayDate) {
                                                    $status = 'Programat';
                                                    $statusClass = 'bg-warning text-dark';
                                                } elseif ($endDate !== null && $endDate < $todayDate) {
                                                    $status = 'Expirat';
                                                    $statusClass = 'bg-danger';
                                                }
                                            @endphp
                                            <span class="d-inline-flex flex-nowrap align-items-center gap-1">
                                                <span class="badge" style="background-color: {{ $role->color }}; color: #fff;">{{ $role->name }}</span>
                                                <span class="badge {{ $statusClass }}">{{ $status }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($user->activ == 0)
                                    <span class="text-danger">Inchis</span>
                                @else
                                    <span class="text-success">Deschis</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex justify-content-end py-0">
                                    <a href="{{ $user->path() }}" class="flex me-1" aria-label="Vizualizeaza {{ $user->name }}">
                                        <span class="badge bg-success"><i class="fa-solid fa-eye"></i></span>
                                    </a>
                                    <a href="{{ $user->path('edit') }}" class="flex me-1" aria-label="Modifica {{ $user->name }}">
                                        <span class="badge bg-primary"><i class="fa-solid fa-edit"></i></span>
                                    </a>
                                    <form method="POST" action="{{ $user->path('destroy') }}" data-confirm="Sigur vrei sa stergi acest utilizator?">
                                        @method('DELETE')
                                        @csrf
                                        <button type="submit" class="badge bg-danger border-0" aria-label="Sterge {{ $user->name }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fa-solid fa-users-slash fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu s-au gasit utilizatori in baza de date.</p>
                                @if($searchNume || $searchTelefon || $searchEmail || $roleId || ($activ !== null && $activ !== ''))
                                    <p class="small mb-0 mt-2">Incearcati sa modificati criteriile de cautare.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->total() > 0)
            <div class="d-flex flex-wrap justify-content-between align-items-center px-3 mt-2 mb-3 small text-muted">
                <span>Afisare {{ $users->firstItem() }}-{{ $users->lastItem() }} din {{ $users->total() }} utilizatori</span>
                <span>Pagina {{ $users->currentPage() }} din {{ $users->lastPage() }}</span>
            </div>
        @endif

        <nav>
            <ul class="pagination justify-content-center">
                {{ $users->appends(Request::except('page'))->links() }}
            </ul>
        </nav>
    </div>
</div>

@endsection
