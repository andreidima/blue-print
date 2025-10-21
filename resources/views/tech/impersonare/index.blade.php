@extends('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-4">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-user-secret"></i> Impersonare utilizatori
            </span>
        </div>

        <div class="col-lg-8">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                <div class="row mb-1 custom-search-form justify-content-end">
                    <div class="col-lg-4">
                        <input type="text" class="form-control rounded-3" id="searchNume" name="searchNume" placeholder="Nume" value="{{ $searchNume }}">
                    </div>
                    <div class="col-lg-4">
                        <input type="text" class="form-control rounded-3" id="searchTelefon" name="searchTelefon" placeholder="Telefon" value="{{ $searchTelefon }}">
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button class="btn btn-sm btn-primary text-white border border-dark rounded-3" type="submit">
                            <i class="fas fa-search text-white me-1"></i>Caută
                        </button>
                    </div>
                    <div class="col-lg-2 d-grid">
                        <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ url()->current() }}" role="button">
                            <i class="far fa-trash-alt text-white me-1"></i>Resetează
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body px-0 py-3">
        @include('errors.errors')

        <div class="alert alert-info mx-3" role="status">
            <i class="fa-solid fa-circle-info me-1"></i>
            Selectează un utilizator pentru a-i prelua drepturile. Vei putea reveni la contul tău din meniul de autentificare.
        </div>

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Tabela impersonare utilizatori">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2" width="5%"><i class="fa-solid fa-hashtag"></i></th>
                        <th scope="col" class="text-white culoare2" width="25%"><i class="fa-solid fa-user me-1"></i> Nume</th>
                        <th scope="col" class="text-white culoare2" width="15%"><i class="fa-solid fa-phone me-1"></i> Telefon</th>
                        <th scope="col" class="text-white culoare2" width="25%"><i class="fa-solid fa-envelope me-1"></i> Email</th>
                        <th scope="col" class="text-white culoare2" width="10%"><i class="fa-solid fa-user-tag me-1"></i> Rol</th>
                        <th scope="col" class="text-white culoare2" width="10%"><i class="fa-solid fa-toggle-on me-1"></i> Stare cont</th>
                        <th scope="col" class="text-white culoare2 text-end" width="10%"><i class="fa-solid fa-user-gear me-1"></i> Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ ($users->currentPage() - 1) * $users->perPage() + $loop->index + 1 }}</td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->telefon }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role }}</td>
                            <td>
                                @if ($user->activ == 0)
                                    <span class="text-danger">Închis</span>
                                @else
                                    <span class="text-success">Deschis</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($user->id === $currentUserId)
                                    <span class="badge bg-secondary"><i class="fa-solid fa-circle-check me-1"></i> Cont curent</span>
                                @elseif ($user->activ == 0)
                                    <span class="badge bg-secondary"><i class="fa-solid fa-ban me-1"></i> Inactiv</span>
                                @else
                                    <form method="POST" action="{{ route('tech.impersonation.start', $user) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="fa-solid fa-user-secret me-1"></i> Impersonează
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fa-solid fa-users-slash fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu s-au găsit utilizatori în baza de date.</p>
                                @if ($searchNume || $searchTelefon)
                                    <p class="small mb-0 mt-2">Încercați să modificați criteriile de căutare.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav>
            <ul class="pagination justify-content-center">
                {{ $users->links() }}
            </ul>
        </nav>
    </div>
</div>
@endsection
