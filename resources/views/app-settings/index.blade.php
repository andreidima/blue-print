@extends ('layouts.app')

@section('content')
@php
    $canWriteAppSettings = auth()->user()?->hasPermission('app-settings.write') ?? false;
    $currentSort = $sort ?? 'label';
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
                <i class="fa-solid fa-sliders me-1"></i> Setari aplicatie
            </span>
        </div>

        <div class="col-lg-6">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-4 mb-1">
                        <input type="text" class="form-control rounded-3" name="searchKey" placeholder="Cheie" value="{{ $searchKey }}">
                    </div>
                    <div class="col-lg-4 mb-1">
                        <input type="text" class="form-control rounded-3" name="searchLabel" placeholder="Nume" value="{{ $searchLabel }}">
                    </div>
                    <div class="col-lg-4 mb-1">
                        <select class="form-select rounded-3" name="type">
                            <option value="">Tip</option>
                            @foreach ($typeOptions as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}" {{ (string) $type === (string) $typeValue ? 'selected' : '' }}>{{ $typeLabel }}</option>
                            @endforeach
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
            @if ($canWriteAppSettings)
                <a class="btn btn-sm btn-success text-white border border-dark rounded-3 me-2" href="{{ route('app-settings.create') }}">
                    <i class="fa-solid fa-plus me-1"></i> Adauga
                </a>
            @endif
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Setari aplicatie">
                <thead class="text-white rounded">
                    <tr class="thead-danger">
                        <th scope="col" class="text-white culoare2 text-nowrap">#</th>
                        <th scope="col" class="text-white culoare2 text-nowrap">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'label', 'dir' => $sortDirFor('label')]) }}">
                                Nume <i class="fa-solid {{ $sortIcon('label') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'key', 'dir' => $sortDirFor('key')]) }}">
                                Cheie <i class="fa-solid {{ $sortIcon('key') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'type', 'dir' => $sortDirFor('type')]) }}">
                                Tip <i class="fa-solid {{ $sortIcon('type') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap">Valoare</th>
                        <th scope="col" class="text-white culoare2 text-nowrap">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'updated_at', 'dir' => $sortDirFor('updated_at')]) }}">
                                Actualizat <i class="fa-solid {{ $sortIcon('updated_at') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap text-end">Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($appSettings as $setting)
                        <tr>
                            <td>{{ ($appSettings->currentPage() - 1) * $appSettings->perPage() + $loop->index + 1 }}</td>
                            <td>{{ $setting->label }}</td>
                            <td><code>{{ $setting->key }}</code></td>
                            <td>{{ $typeOptions[$setting->type] ?? $setting->type }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($setting->value ?? '-', 100) }}</td>
                            <td>{{ optional($setting->updated_at)->format('d.m.Y H:i') }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a class="btn btn-sm btn-primary text-white" href="{{ route('app-settings.edit', $setting) }}">
                                        <i class="fa-solid fa-pen me-1"></i> Editeaza
                                    </a>
                                    @if ($canWriteAppSettings)
                                        <form method="POST" action="{{ route('app-settings.destroy', $setting) }}" data-confirm="Sigur vrei sa stergi aceasta setare?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger text-white">
                                                <i class="fa-solid fa-trash me-1"></i> Sterge
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Nu exista setari salvate.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav>
            <ul class="pagination justify-content-center">
                {{ $appSettings->appends(Request::except('page'))->links() }}
            </ul>
        </nav>
    </div>
</div>
@endsection
