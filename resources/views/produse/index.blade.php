@extends ('layouts.app')

@section('content')
@php
    $canWriteProduse = auth()->user()?->hasPermission('produse.write') ?? false;
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-boxes-stacked"></i> Produse
            </span>
        </div>

        <div class="col-lg-6">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-6">
                        <input type="text" class="form-control rounded-3" id="search" name="search" placeholder="Denumire" value="{{ $search }}">
                    </div>
                    <div class="col-lg-6">
                        <select class="form-select rounded-3" name="activ">
                            <option value="">Toate</option>
                            <option value="1" {{ $activ === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ $activ === '0' ? 'selected' : '' }}>Inactive</option>
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
                <a class="btn btn-sm btn-success text-white border border-dark rounded-3 col-md-8" href="{{ route('produse.create') }}" role="button">
                    <i class="fas fa-plus text-white me-1"></i> Adauga produs
                </a>
            @endif
        </div>
    </div>

    <div class="card-body px-0 py-3">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Produse table">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2" width="5%"><i class="fa-solid fa-hashtag"></i></th>
                        <th scope="col" class="text-white culoare2" width="50%"><i class="fa-solid fa-box me-1"></i> Denumire</th>
                        <th scope="col" class="text-white culoare2" width="15%"><i class="fa-solid fa-tag me-1"></i> Pret</th>
                        <th scope="col" class="text-white culoare2" width="10%"><i class="fa-solid fa-toggle-on me-1"></i> Activ</th>
                        <th scope="col" class="text-white culoare2 text-end" width="20%"><i class="fa-solid fa-cogs me-1"></i> Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($produse as $produs)
                        <tr>
                            <td>
                                {{ ($produse->currentpage()-1) * $produse->perpage() + $loop->index + 1 }}
                            </td>
                            <td>
                                {{ $produs->denumire }}
                            </td>
                            <td>
                                {{ number_format($produs->pret, 2) }}
                            </td>
                            <td>
                                @if ($produs->activ)
                                    <span class="badge bg-success">Da</span>
                                @else
                                    <span class="badge bg-secondary">Nu</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex justify-content-end py-0">
                                    <a href="{{ route('produse.show', $produs) }}" class="flex me-1" aria-label="Vezi {{ $produs->denumire }}">
                                        <span class="badge bg-success"><i class="fa-solid fa-eye"></i></span>
                                    </a>
                                    <a href="{{ route('produse.edit', $produs) }}" class="flex me-1" aria-label="Modifica {{ $produs->denumire }}">
                                        <span class="badge bg-primary"><i class="fa-solid fa-edit"></i></span>
                                    </a>
                                    @if ($canWriteProduse)
                                        <form method="POST" action="{{ route('produse.destroy', $produs) }}" onsubmit="return confirm('Sigur vrei sa stergi acest produs?')">
                                            @method('DELETE')
                                            @csrf
                                            <button type="submit" class="badge bg-danger border-0" aria-label="Sterge {{ $produs->denumire }}">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fa-solid fa-box-open fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu s-au gasit produse in baza de date.</p>
                                @if($search || $activ !== null)
                                    <p class="small mb-0 mt-2">Incearca sa modifici criteriile de cautare.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav>
            <ul class="pagination justify-content-center">
                {{ $produse->appends(Request::except('page'))->links() }}
            </ul>
        </nav>
    </div>
</div>
@endsection
