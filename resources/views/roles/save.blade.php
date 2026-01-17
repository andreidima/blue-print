@extends ('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="shadow-lg" style="border-radius: 40px 40px 40px 40px;">
                <div class="border border-secondary p-2 culoare2" style="border-radius: 40px 40px 0px 0px;">
                    <span class="badge text-light fs-5">
                        <i class="fa-solid fa-user-tag me-1"></i>
                        {{ isset($role) ? 'Editează Rol' : 'Adaugă Rol' }}
                    </span>
                </div>

                @include ('errors.errors')

                <div class="card-body py-3 px-4 border border-secondary"
                    style="border-radius: 0px 0px 40px 40px;"
                >
                    <form class="needs-validation" novalidate
                          method="POST"
                          action="{{ isset($role) ? route('roles.update', $role->id) : route('roles.store') }}">
                        @csrf
                        @if(isset($role))
                            @method('PUT')
                        @endif

                        @include ('roles.form', [
                            'role' => $role ?? null,
                            'buttonText' => isset($role) ? 'Salvează modificările' : 'Adaugă Rol',
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

