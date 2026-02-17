@extends ('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="shadow-lg" style="border-radius: 40px 40px 40px 40px;">
                <div class="border border-secondary p-2 culoare2" style="border-radius: 40px 40px 0px 0px;">
                    <span class="badge text-light fs-5">
                        <i class="fa-solid fa-user-{{ isset($user) ? 'edit' : 'plus' }} me-1"></i>
                        {{ isset($user) ? 'Editează Utilizator' : 'Adaugă Utilizator' }}
                    </span>
                </div>

                @include ('errors.errors')

                <div class="card-body py-3 px-4 border border-secondary"
                    style="border-radius: 0px 0px 40px 40px;"
                >
                    <form class="needs-validation" novalidate
                          method="POST"
                          action="{{ isset($user) ? route('users.update', $user->id) : route('users.store') }}">
                        @csrf
                        @if(isset($user))
                            @method('PUT')
                        @endif

                        @include ('users.form', [
                            'user' => $user ?? null,
                            'buttonText' => isset($user) ? 'Salvează modificările' : 'Adaugă Utilizator',
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
