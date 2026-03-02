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
                <i class="fa-solid fa-print me-1"></i> Echipamente
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
                <a class="btn btn-sm btn-success text-white border border-dark rounded-3 col-md-8" href="{{ route('echipamente.create') }}" role="button">
                    <i class="fas fa-plus text-white me-1"></i> Adauga echipament
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
                        <th scope="col" class="text-white culoare2" width="50%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'denumire', 'dir' => $sortDirFor('denumire')]) }}">
                                Denumire <i class="fa-solid {{ $sortIcon('denumire') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2" width="15%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'activ', 'dir' => $sortDirFor('activ')]) }}">
                                Activ <i class="fa-solid {{ $sortIcon('activ') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2" width="20%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'dir' => $sortDirFor('created_at')]) }}">
                                Creat la <i class="fa-solid {{ $sortIcon('created_at') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-end" width="10%">Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($echipamente as $echipament)
                        <tr>
                            <td>{{ ($echipamente->currentpage()-1) * $echipamente->perpage() + $loop->index + 1 }}</td>
                            <td>{{ $echipament->denumire }}</td>
                            <td>{{ $echipament->activ ? 'Da' : 'Nu' }}</td>
                            <td>{{ optional($echipament->created_at)->format('d.m.Y') }}</td>
                            <td>
                                <div class="d-flex justify-content-end py-0">
                                    <a href="{{ route('echipamente.show', $echipament) }}" class="flex me-1" aria-label="Vezi {{ $echipament->denumire }}">
                                        <span class="badge bg-success"><i class="fa-solid fa-eye"></i></span>
                                    </a>
                                    <a href="{{ route('echipamente.edit', $echipament) }}" class="flex me-1" aria-label="Modifica {{ $echipament->denumire }}">
                                        <span class="badge bg-primary"><i class="fa-solid fa-edit"></i></span>
                                    </a>
                                    @if ($canWriteProduse)
                                        <form method="POST" action="{{ route('echipamente.destroy', $echipament) }}" data-confirm="Sigur vrei sa stergi acest echipament?">
                                            @method('DELETE')
                                            @csrf
                                            <button type="submit" class="badge bg-danger border-0" aria-label="Sterge {{ $echipament->denumire }}">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">Nu s-au gasit echipamente.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <nav>
            <ul class="pagination justify-content-center">
                {{ $echipamente->appends(Request::except('page'))->links() }}
            </ul>
        </nav>
    </div>
</div>
@endsection
