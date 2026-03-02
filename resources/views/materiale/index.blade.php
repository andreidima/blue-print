@extends ('layouts.app')

@section('content')
@php
    $canWriteProduse = auth()->user()?->hasPermission('produse.write') ?? false;
    $currentSort = $sort ?? 'denumire';
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
                <i class="fa-solid fa-layer-group me-1"></i> Materiale
            </span>
        </div>
        <div class="col-lg-6">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-8">
                        <input type="text" class="form-control rounded-3" id="search" name="search" placeholder="Denumire" value="{{ $search }}">
                    </div>
                    <div class="col-lg-4">
                        <select class="form-select rounded-3" name="activ">
                            <option value="">Toate</option>
                            <option value="1" {{ (string) $activ === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ (string) $activ === '0' ? 'selected' : '' }}>Inactive</option>
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
            @if ($canWriteProduse)
                <a class="btn btn-sm btn-success text-white border border-dark rounded-3 col-md-8" href="{{ route('materiale.create') }}" role="button">
                    <i class="fas fa-plus text-white me-1"></i> Adauga material
                </a>
            @endif
        </div>
    </div>

    <div class="card-body px-0 py-3">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded">
                <thead class="text-white rounded">
                    <tr class="thead-danger">
                        <th scope="col" class="text-white culoare2" width="5%">#</th>
                        <th scope="col" class="text-white culoare2" width="35%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'denumire', 'dir' => $sortDirFor('denumire')]) }}">
                                Denumire <i class="fa-solid {{ $sortIcon('denumire') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2" width="15%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'unitate_masura', 'dir' => $sortDirFor('unitate_masura')]) }}">
                                UM <i class="fa-solid {{ $sortIcon('unitate_masura') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2" width="20%">Descriere</th>
                        <th scope="col" class="text-white culoare2" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'activ', 'dir' => $sortDirFor('activ')]) }}">
                                Activ <i class="fa-solid {{ $sortIcon('activ') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2" width="15%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'dir' => $sortDirFor('created_at')]) }}">
                                Creat la <i class="fa-solid {{ $sortIcon('created_at') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-end" width="10%">Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($materiale as $material)
                        <tr>
                            <td>{{ ($materiale->currentpage()-1) * $materiale->perpage() + $loop->index + 1 }}</td>
                            <td>{{ $material->denumire }}</td>
                            <td>{{ $material->unitate_masura }}</td>
                            <td>{{ $material->descriere ? \Illuminate\Support\Str::limit($material->descriere, 90) : '-' }}</td>
                            <td>{{ $material->activ ? 'Da' : 'Nu' }}</td>
                            <td>{{ optional($material->created_at)->format('d.m.Y') }}</td>
                            <td>
                                <div class="d-flex justify-content-end py-0">
                                    <a href="{{ route('materiale.show', $material) }}" class="flex me-1" aria-label="Vezi {{ $material->denumire }}">
                                        <span class="badge bg-success"><i class="fa-solid fa-eye"></i></span>
                                    </a>
                                    <a href="{{ route('materiale.edit', $material) }}" class="flex me-1" aria-label="Modifica {{ $material->denumire }}">
                                        <span class="badge bg-primary"><i class="fa-solid fa-edit"></i></span>
                                    </a>
                                    @if ($canWriteProduse)
                                        <form method="POST" action="{{ route('materiale.destroy', $material) }}" data-confirm="Sigur vrei sa stergi acest material?">
                                            @method('DELETE')
                                            @csrf
                                            <button type="submit" class="badge bg-danger border-0" aria-label="Sterge {{ $material->denumire }}">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">Nu s-au gasit materiale.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <nav>
            <ul class="pagination justify-content-center">
                {{ $materiale->appends(Request::except('page'))->links() }}
            </ul>
        </nav>
    </div>
</div>
@endsection
