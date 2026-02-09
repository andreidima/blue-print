@extends ('layouts.app')

@section('content')
@php
    $canWriteClienti = auth()->user()?->hasPermission('clienti.write') ?? false;
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
                    <div class="col-lg-12">
                        <input type="text" class="form-control rounded-3" id="search" name="search" placeholder="Nume, telefon, email" value="{{ $search }}">
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
            @if ($canWriteClienti)
                <a class="btn btn-sm btn-success text-white border border-dark rounded-3 col-md-8" href="{{ route('clienti.create') }}" role="button">
                    <i class="fas fa-user-plus text-white me-1"></i> Adauga client
                </a>
            @endif
        </div>
    </div>

    <div class="card-body px-0 py-3">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Clienti table">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2" width="5%"><i class="fa-solid fa-hashtag"></i></th>
                        <th scope="col" class="text-white culoare2" width="30%"><i class="fa-solid fa-user me-1"></i> Client</th>
                        <th scope="col" class="text-white culoare2" width="20%"><i class="fa-solid fa-phone me-1"></i> Telefon</th>
                        <th scope="col" class="text-white culoare2" width="30%"><i class="fa-solid fa-envelope me-1"></i> Email</th>
                        <th scope="col" class="text-white culoare2 text-end" width="15%"><i class="fa-solid fa-cogs me-1"></i> Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clienti as $client)
                        <tr>
                            <td>
                                {{ ($clienti->currentpage()-1) * $clienti->perpage() + $loop->index + 1 }}
                            </td>
                            <td>
                                {{ $client->nume_complet }}
                            </td>
                            <td>
                                <div>{{ $client->telefon }}</div>
                                @if ($client->telefon_secundar)
                                    <div class="small text-muted">{{ $client->telefon_secundar }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $client->email }}
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
                                        <form method="POST" action="{{ route('clienti.destroy', $client) }}" onsubmit="return confirm('Sigur vrei sa stergi acest client?')">
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
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fa-solid fa-user-slash fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu s-au gasit clienti in baza de date.</p>
                                @if($search)
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
                {{ $clienti->appends(Request::except('page'))->links() }}
            </ul>
        </nav>
    </div>
</div>
@endsection
