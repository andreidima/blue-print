@extends('layouts.app')

@section('content')
<div class="login-shell">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <div class="login-hero h-100 p-4 p-lg-5 rounded-4">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
                        <span class="login-pill">Pagina de pornire</span>
                        <span class="login-pill alt">Status: Pregătit</span>
                    </div>
                    <h1 class="display-6 fw-bold mb-3">
                        Bine ai venit la {{ config('app.name', 'Laravel') }}
                    </h1>
                    <p class="lead mb-4">
                        Un singur loc pentru comenzi, clienți și produse. Intră în contul tău și continuă
                        exact de unde ai rămas.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="login-feature">
                                <div class="login-feature-icon">
                                    <i class="fa-solid fa-clipboard-list"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Comenzi rapide</div>
                                    <div class="small text-muted">
                                        Creează, editează și urmărește fiecare pas al comenzii.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-feature">
                                <div class="login-feature-icon">
                                    <i class="fa-solid fa-address-book"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Clienți organizați</div>
                                    <div class="small text-muted">
                                        Păstrează istoricul și livrările într-un singur loc.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-feature">
                                <div class="login-feature-icon">
                                    <i class="fa-solid fa-boxes-stacked"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Produse la zi</div>
                                    <div class="small text-muted">
                                        Evidență centralizată pentru stocuri și prețuri.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-feature">
                                <div class="login-feature-icon">
                                    <i class="fa-solid fa-file-circle-question"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Cereri de ofertă</div>
                                    <div class="small text-muted">
                                        Centralizează solicitările și răspunde rapid.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="login-cta mt-4">
                        <div class="login-check">
                            <i class="fa-solid fa-check"></i>
                            <span>Acces securizat cu roluri și permisiuni</span>
                        </div>
                        <div class="login-check">
                            <i class="fa-solid fa-check"></i>
                            <span>Fluxuri rapide pentru echipa ta</span>
                        </div>
                        <div class="login-check">
                            <i class="fa-solid fa-check"></i>
                            <span>Totul este pregătit pentru a începe</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card login-card border-0 shadow h-100">
                    <div class="card-header login-card-header text-center">
                        <div class="login-logo">
                            <i class="fa-solid fa-layer-group"></i>
                        </div>
                        <div class="fs-4 fw-semibold mt-3">
                            {{ config('app.name', 'Laravel') }}
                        </div>
                        <div class="small opacity-75">
                            Intră în cont pentru a continua
                        </div>
                    </div>
                    <div class="card-body pb-0">
                        @include ('errors.errors')

                        <div class="login-helper mb-3">
                            Folosește e-mailul de serviciu. Dacă ai nevoie de acces, contactează administratorul.
                        </div>

                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text culoare1" id="inputGroupPrepend2">
                                            <i class="fas fa-user culoare1"></i>
                                        </span>
                                        <input id="email" type="text" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" autocomplete="email" autofocus
                                            placeholder="{{ __('auth.E-Mail Address') }}"
                                        >
                                    </div>
                                    @error('email')
                                        <span class="text-danger" role="alert">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text culoare1" id="inputGroupPrepend2">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" autocomplete="current-password"
                                            placeholder="{{ __('auth.Password') }}"
                                        >
                                    </div>
                                    @error('password')
                                        <span class="text-danger" role="alert">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-12 text-center d-grid gap-2 mx-auto">
                                    <div class="d-flex justify-content-center my-0">
                                        <div class="form-check d-inline-block">
                                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="remember">
                                                {{ __('auth.Remember Me') }}
                                            </label>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-8 d-grid gap-2 mx-auto">
                                    <button type="submit" class="btn login-submit text-white mb-2 fs-5 shadow-sm rounded-3">
                                        {{ __('auth.Login') }}
                                    </button>
                                </div>
                            </div>

                            @if (Route::has('password.request'))
                                <div class="row mb-2">
                                    <div class="col-md-12 text-center">
                                        <hr>
                                        <a class="btn btn-link p-0 m-0 border-0" href="{{ route('password.request') }}">
                                            {{ __('auth.Forgot Your Password?') }}
                                        </a>
                                    </div>
                                </div>
                            @endif

                            @if (Route::has('register'))
                                <div class="row mb-2">
                                    <div class="col-md-12 text-center">
                                        Nu ai cont?
                                        <a class="" href="{{ route('register') }}">Inregistreaza-te</a>
                                    </div>
                                </div>
                            @endif

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
