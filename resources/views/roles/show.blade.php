@extends ('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="shadow-lg" style="border-radius: 40px;">
                <div class="border border-secondary p-2 culoare2" style="border-radius: 40px 40px 0px 0px;">
                    <span class="badge text-light fs-5">
                        <i class="fa-solid fa-user-tag me-1"></i> Detalii Rol
                    </span>
                </div>

                <div class="card-body border border-secondary p-4" style="border-radius: 0px 0px 40px 40px;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Nume:</strong> {{ $role->name }}
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Slug:</strong> <code>{{ $role->slug }}</code>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Culoare:</strong>
                            <span class="badge" style="background-color: {{ $role->color }}; color: #fff;">
                                {{ $role->color }}
                            </span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Creat la:</strong> {{ $role->created_at?->format('d.m.Y H:i') }}
                        </div>
                    </div>

                    <div class="d-flex justify-content-center mt-4">
                        <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-primary text-white me-3 rounded-3">
                            <i class="fa-solid fa-edit me-1"></i> Modifică
                        </a>
                        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('roles.index')) }}">
                            <i class="fa-solid fa-arrow-left me-1"></i> Înapoi
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

