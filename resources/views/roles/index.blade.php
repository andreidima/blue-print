@extends ('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-user-tag"></i> Roluri
            </span>
        </div>

        <div class="col-lg-6">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                @csrf
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-8">
                        <input type="text" class="form-control rounded-3" id="search" name="search" placeholder="Caută rol" value="{{ $search }}">
                    </div>
                    <div class="col-lg-4">
                        <button class="btn btn-sm w-100 btn-primary text-white border border-dark rounded-3" type="submit">
                            <i class="fas fa-search text-white me-1"></i>Caută
                        </button>
                    </div>
                </div>
                <div class="row custom-search-form justify-content-center">
                    <div class="col-lg-4">
                        <a class="btn btn-sm w-100 btn-secondary text-white border border-dark rounded-3" href="{{ url()->current() }}" role="button">
                            <i class="far fa-trash-alt text-white me-1"></i>Resetează căutarea
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-3 text-end">
            <a class="btn btn-sm btn-success text-white border border-dark rounded-3 col-md-8" href="{{ route('roles.create') }}" role="button">
                <i class="fas fa-plus text-white me-1"></i> Adaugă rol
            </a>
        </div>
    </div>

    <div class="card-body px-0 py-3">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Roles table">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2" width="5%"><i class="fa-solid fa-hashtag"></i></th>
                        <th scope="col" class="text-white culoare2" width="35%"><i class="fa-solid fa-user-tag me-1"></i> Nume</th>
                        <th scope="col" class="text-white culoare2" width="25%"><i class="fa-solid fa-link me-1"></i> Slug</th>
                        <th scope="col" class="text-white culoare2" width="20%"><i class="fa-solid fa-palette me-1"></i> Culoare</th>
                        <th scope="col" class="text-white culoare2 text-end" width="15%"><i class="fa-solid fa-cogs me-1"></i> Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td>{{ ($roles->currentpage()-1) * $roles->perpage() + $loop->index + 1 }}</td>
                            <td>
                                <span class="badge" style="background-color: {{ $role->color }}; color: #fff;">
                                    {{ $role->name }}
                                </span>
                            </td>
                            <td><code>{{ $role->slug }}</code></td>
                            <td>
                                <span class="badge" style="background-color: {{ $role->color }}; color: #fff;">
                                    {{ $role->color }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-end py-0">
                                    <a href="{{ route('roles.show', $role->id) }}" class="flex me-1" aria-label="Vizualizează {{ $role->name }}">
                                        <span class="badge bg-success"><i class="fa-solid fa-eye"></i></span>
                                    </a>
                                    <a href="{{ route('roles.edit', $role->id) }}" class="flex me-1" aria-label="Modifică {{ $role->name }}">
                                        <span class="badge bg-primary"><i class="fa-solid fa-edit"></i></span>
                                    </a>
                                    <a href="#"
                                       data-bs-toggle="modal"
                                       data-bs-target="#stergeRol{{ $role->id }}"
                                       aria-label="Șterge {{ $role->name }}">
                                        <span class="badge bg-danger"><i class="fa-solid fa-trash"></i></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fa-solid fa-tags fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu s-au găsit roluri în baza de date.</p>
                                @if($search)
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
                {{ $roles->links() }}
            </ul>
        </nav>
    </div>
</div>

@foreach ($roles as $role)
    <div class="modal fade text-dark" id="stergeRol{{ $role->id }}" tabindex="-1" role="dialog" aria-labelledby="stergeRolLabel{{ $role->id }}" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white" id="stergeRolLabel{{ $role->id }}">
                        <i class="fa-solid fa-tag me-1"></i> Șterge: {{ $role->name }}
                    </h5>
                    <button type="button" class="btn-close bg-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-start">
                    Ești sigur că vrei să ștergi acest rol?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Renunță</button>
                    <form method="POST" action="{{ route('roles.destroy', $role->id) }}">
                        @method('DELETE')
                        @csrf
                        <button type="submit" class="btn btn-danger text-white">
                            <i class="fa-solid fa-trash me-1"></i> Șterge Rol
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endforeach

@endsection

