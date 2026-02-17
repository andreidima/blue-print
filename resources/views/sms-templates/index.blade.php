@extends ('layouts.app')

@section('content')
@php
    $canWriteSmsTemplates = auth()->user()?->hasPermission('sms-templates.write') ?? false;
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
                <i class="fa-solid fa-comment-sms me-1"></i> Template-uri SMS
            </span>
        </div>

        <div class="col-lg-6">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-4 mb-1">
                        <input type="text" class="form-control rounded-3" name="searchKey" placeholder="Cheie" value="{{ $searchKey }}">
                    </div>
                    <div class="col-lg-4 mb-1">
                        <input type="text" class="form-control rounded-3" name="searchName" placeholder="Nume" value="{{ $searchName }}">
                    </div>
                    <div class="col-lg-4 mb-1">
                        <select class="form-select rounded-3" name="active">
                            <option value="">Stare</option>
                            <option value="1" {{ (string) $active === '1' ? 'selected' : '' }}>Activ</option>
                            <option value="0" {{ (string) $active === '0' ? 'selected' : '' }}>Inactiv</option>
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
            @if ($canWriteSmsTemplates)
                <a class="btn btn-sm btn-success text-white border border-dark rounded-3 me-2" href="{{ route('sms-templates.create') }}">
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
            <table class="table table-striped table-hover rounded" aria-label="Templateuri SMS">
                <thead class="text-white rounded">
                    <tr class="thead-danger">
                        <th scope="col" class="text-white culoare2 text-nowrap">#</th>
                        <th scope="col" class="text-white culoare2 text-nowrap">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'dir' => $sortDirFor('name')]) }}">
                                Nume <i class="fa-solid {{ $sortIcon('name') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap">Culoare</th>
                        <th scope="col" class="text-white culoare2 text-nowrap">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'key', 'dir' => $sortDirFor('key')]) }}">
                                Cheie <i class="fa-solid {{ $sortIcon('key') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'active', 'dir' => $sortDirFor('active')]) }}">
                                Activ <i class="fa-solid {{ $sortIcon('active') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap">Mesaj</th>
                        <th scope="col" class="text-white culoare2 text-nowrap">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'dir' => $sortDirFor('created_at')]) }}">
                                Data adaugarii <i class="fa-solid {{ $sortIcon('created_at') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap text-end">Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($smsTemplates as $template)
                        <tr>
                            <td>{{ ($smsTemplates->currentPage() - 1) * $smsTemplates->perPage() + $loop->index + 1 }}</td>
                            <td>{{ $template->name }}</td>
                            <td>
                                <span class="d-inline-block rounded-circle" style="width:18px; height:18px; background-color:{{ $template->color ?? '#6c757d' }};"></span>
                            </td>
                            <td><code>{{ $template->key }}</code></td>
                            <td>
                                <span class="badge {{ $template->active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $template->active ? 'Da' : 'Nu' }}
                                </span>
                            </td>
                            <td>{{ \Illuminate\Support\Str::limit($template->body, 140) }}</td>
                            <td>{{ optional($template->created_at)->format('d.m.Y H:i') }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a class="btn btn-sm btn-primary text-white" href="{{ route('sms-templates.edit', $template) }}">
                                        <i class="fa-solid fa-pen me-1"></i> Editeaza
                                    </a>
                                    @if ($canWriteSmsTemplates)
                                        <form method="POST" action="{{ route('sms-templates.destroy', $template) }}" data-confirm="Sigur vrei sa stergi acest template?">
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
                            <td colspan="8" class="text-center text-muted py-4">Nu exista template-uri SMS.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav>
            <ul class="pagination justify-content-center">
                {{ $smsTemplates->appends(Request::except('page'))->links() }}
            </ul>
        </nav>
    </div>
</div>
@endsection
