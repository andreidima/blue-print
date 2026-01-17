@extends ('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-users"></i> Utilizatori
            </span>
        </div>

        {{-- Search form --}}
        <div class="col-lg-6">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                @csrf
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-6">
                        <input type="text" class="form-control rounded-3" id="searchNume" name="searchNume" placeholder="Nume" value="{{ $searchNume }}">
                    </div>
                    <div class="col-lg-6">
                        <input type="text" class="form-control rounded-3" id="searchTelefon" name="searchTelefon" placeholder="Telefon" value="{{ $searchTelefon }}">
                    </div>
                </div>
                <div class="row custom-search-form justify-content-center">
                    <div class="col-lg-4">
                        <button class="btn btn-sm w-100 btn-primary text-white border border-dark rounded-3" type="submit">
                            <i class="fas fa-search text-white me-1"></i>Caută
                        </button>
                    </div>
                    <div class="col-lg-4">
                        <a class="btn btn-sm w-100 btn-secondary text-white border border-dark rounded-3" href="{{ url()->current() }}" role="button">
                            <i class="far fa-trash-alt text-white me-1"></i>Resetează căutarea
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- Buton to add new user --}}
        <div class="col-lg-3 text-end">
            <a class="btn btn-sm btn-success text-white border border-dark rounded-3 col-md-8" href="{{ url()->current() }}/adauga" role="button">
                <i class="fas fa-user-plus text-white me-1"></i> Adaugă utilizator
            </a>
        </div>
    </div>

    {{-- Card Body --}}
    <div class="card-body px-0 py-3">

        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Users table">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2" width="5%"><i class="fa-solid fa-hashtag"></i></th>
                        <th scope="col" class="text-white culoare2" width="25%"><i class="fa-solid fa-user me-1"></i> Nume</th>
                        <th scope="col" class="text-white culoare2" width="15%"><i class="fa-solid fa-phone me-1"></i> Telefon</th>
                        <th scope="col" class="text-white culoare2" width="25%"><i class="fa-solid fa-envelope me-1"></i> Email</th>
                        <th scope="col" class="text-white culoare2" width="10%"><i class="fa-solid fa-user-tag me-1"></i> Roluri</th>
                        <th scope="col" class="text-white culoare2" width="10%"><i class="fa-solid fa-toggle-on me-1"></i> Stare cont</th>
                        <th scope="col" class="text-white culoare2 text-end" width="10%"><i class="fa-solid fa-cogs me-1"></i> Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td align="">
                                {{ ($users ->currentpage()-1) * $users ->perpage() + $loop->index + 1 }}
                            </td>
                            <td class="">
                                {{ $user->name }}
                            </td>
                            <td class="">
                                {{ $user->telefon }}
                            </td>
                            <td class="">
                                {{ $user->email }}
                            </td>
                            <td class="">
                                @php
                                    $visibleRoles = $user->roles->where('slug', '!=', 'superadmin');
                                @endphp
                                @forelse ($visibleRoles as $role)
                                    <span class="badge me-1" style="background-color: {{ $role->color }}; color: #fff;">
                                        {{ $role->name }}
                                    </span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td>
                                @if ($user->activ == 0)
                                    <span class="text-danger">Închis</span>
                                @else
                                    <span class="text-success">Deschis</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex justify-content-end py-0">
                                    <a href="{{ $user->path() }}" class="flex me-1" aria-label="Vizualizează {{ $user->name }}">
                                        <span class="badge bg-success"><i class="fa-solid fa-eye"></i></span>
                                    </a>
                                    <a href="{{ $user->path('edit') }}" class="flex me-1" aria-label="Modifică {{ $user->name }}">
                                        <span class="badge bg-primary"><i class="fa-solid fa-edit"></i></span>
                                    </a>
                                    <a href="#"
                                       data-bs-toggle="modal"
                                       data-bs-target="#stergeUtilizator{{ $user->id }}"
                                       aria-label="Șterge {{ $user->name }}">
                                        <span class="badge bg-danger"><i class="fa-solid fa-trash"></i></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fa-solid fa-users-slash fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu s-au găsit utilizatori în baza de date.</p>
                                @if($searchNume || $searchTelefon)
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
                {{ $users->appends(Request::except('page'))->links() }}
            </ul>
        </nav>
    </div>
</div>

{{-- Modals to delete users --}}
@foreach ($users as $user)
    <div class="modal fade text-dark" id="stergeUtilizator{{ $user->id }}" tabindex="-1" role="dialog" aria-labelledby="stergeUtilizatorLabel{{ $user->id }}" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white" id="stergeUtilizatorLabel{{ $user->id }}">
                        <i class="fa-solid fa-user-minus me-1"></i> Șterge: {{ $user->name }}
                    </h5>
                    <button type="button" class="btn-close bg-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-start">
                    Ești sigur că vrei să ștergi acest utilizator?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Renunță</button>
                    <form method="POST" action="{{ $user->path('destroy') }}">
                        @method('DELETE')
                        @csrf
                        <button type="submit" class="btn btn-danger text-white">
                            <i class="fa-solid fa-trash me-1"></i> Șterge Utilizator
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endforeach

@endsection
