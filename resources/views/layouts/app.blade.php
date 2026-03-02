<!doctype html>
<html class="h-100" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.scss', 'resources/js/app.js'])

    <!-- Font Awesome links -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body class="d-flex flex-column h-100">
    @auth
    <header>
        <nav class="navbar navbar-lg navbar-expand-lg navbar-dark shadow culoare1">
            <div class="container">
                <a class="navbar-brand me-5" href="{{ url('/') }}">
                    {{ config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        @php
                            $routeComanda = request()->route('comanda');
                            $isCerereOfertaContext = request()->routeIs('comenzi.*')
                                && $routeComanda instanceof \App\Models\Comanda
                                && $routeComanda->tip === \App\Enums\TipComanda::CerereOferta->value;
                            $comenziMenuActive = request()->routeIs('comenzi.*') && !$isCerereOfertaContext;
                            $cereriOfertaMenuActive = request()->routeIs('cereri-oferta', 'cereri-oferta.*') || $isCerereOfertaContext;
                        @endphp
                        <li class="nav-item me-3">
                            <a
                                class="nav-link {{ request()->routeIs('acasa') ? 'active' : '' }}"
                                @if (request()->routeIs('acasa')) aria-current="page" @endif
                                href="{{ route('acasa') }}"
                            >
                                <i class="fa-solid fa-house me-1"></i> Acasă
                            </a>
                        </li>
                        <li class="nav-item me-3">
                            <a class="nav-link {{ $comenziMenuActive ? 'active' : '' }}" href="{{ route('comenzi.index') }}">
                                <i class="fa-solid fa-clipboard-list me-1"></i> Comenzi
                            </a>
                        </li>
                        <li class="nav-item me-3">
                            <a class="nav-link {{ $cereriOfertaMenuActive ? 'active' : '' }}" href="{{ route('cereri-oferta') }}">
                                <i class="fa-solid fa-file-circle-question me-1"></i> Cereri ofertă
                                @if (!empty($cereriOfertaDeschise))
                                    <span class="badge bg-warning text-dark ms-1">{{ $cereriOfertaDeschise }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item me-3">
                            <a class="nav-link {{ request()->routeIs('clienti.*') ? 'active' : '' }}" href="{{ route('clienti.index') }}">
                                <i class="fa-solid fa-address-book me-1"></i> Clienți
                            </a>
                        </li>
                        @php
                            $produseMenuActive = request()->routeIs('produse.*')
                                || request()->routeIs('nomenclator.*')
                                || request()->routeIs('materiale.*')
                                || request()->routeIs('echipamente.*');
                        @endphp
                        <li class="nav-item me-3 dropdown">
                            <a class="nav-link dropdown-toggle {{ $produseMenuActive ? 'active' : '' }}" href="#" id="produseDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-boxes-stacked me-1"></i> Produse
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="produseDropdown">
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('produse.*') ? 'active' : '' }}" href="{{ route('produse.index') }}">
                                        <i class="fa-solid fa-boxes-stacked me-1"></i> Produse
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('nomenclator.*') ? 'active' : '' }}" href="{{ route('nomenclator.index') }}">
                                        <i class="fa-solid fa-list me-1"></i> Nomenclator
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('materiale.*') ? 'active' : '' }}" href="{{ route('materiale.index') }}">
                                        <i class="fa-solid fa-layer-group me-1"></i> Materiale
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('echipamente.*') ? 'active' : '' }}" href="{{ route('echipamente.index') }}">
                                        <i class="fa-solid fa-print me-1"></i> Echipamente
                                    </a>
                                </li>
                            </ul>
                        </li>
                        @can('admin-action')
                            @php
                                $utileActive = request()->routeIs('users.*')
                                    || request()->routeIs('sms-templates.*')
                                    || request()->routeIs('email-templates.*')
                                    || request()->routeIs('app-settings.*');
                            @endphp
                            <li class="nav-item me-3 dropdown">
                                <a class="nav-link dropdown-toggle {{ $utileActive ? 'active' : '' }}" href="#" id="utileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-screwdriver-wrench me-1"></i> Utile
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="utileDropdown">
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                                            <i class="fa-solid fa-users me-1"></i> Utilizatori
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('sms-templates.*') ? 'active' : '' }}" href="{{ route('sms-templates.index') }}">
                                            <i class="fa-solid fa-comment-sms me-1"></i> Template-uri SMS
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('email-templates.*') ? 'active' : '' }}" href="{{ route('email-templates.index') }}">
                                            <i class="fa-solid fa-envelope me-1"></i> Template-uri Email
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('app-settings.*') ? 'active' : '' }}" href="{{ route('app-settings.index') }}">
                                            <i class="fa-solid fa-sliders me-1"></i> Setari aplicatie
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endcan
                        @if (Auth::user()?->hasAnyRole(['Admin', 'SuperAdmin']))
                            @php
                                $techActive = request()->routeIs('tech.*');
                            @endphp
                            <li class="nav-item me-3 dropdown">
                                <a class="nav-link dropdown-toggle {{ $techActive ? 'active' : '' }}" href="#" id="techDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-microchip me-1"></i> Tech
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="techDropdown">
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('tech.impersonation.*') ? 'active' : '' }}" href="{{ route('tech.impersonation.index') }}">
                                            <i class="fa-solid fa-user-secret me-1"></i> Impersonare utilizatori
                                        </a>
                                    </li>
                                    @can('super-admin-action')
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('tech.cronjobs.*') ? 'active' : '' }}" href="{{ route('tech.cronjobs.index') }}">
                                            <i class="fa-solid fa-clock-rotate-left me-1"></i> Jurnale cronjob
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('tech.migrations.*') ? 'active' : '' }}" href="{{ route('tech.migrations.index') }}">
                                            <i class="fa-solid fa-database me-1"></i> Migrații bază de date
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('tech.cache.*') ? 'active' : '' }}" href="{{ route('tech.cache.index') }}">
                                            <i class="fa-solid fa-broom me-1"></i> Curata cache aplicatie
                                        </a>
                                    </li>
                                    @endcan
                                </ul>
                            </li>
                        @endif
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">Login</a>
                                </li>
                            @endif

                            {{-- @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif --}}
                        @else
                            <li class="nav-item me-3">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-light position-relative"
                                    data-bs-toggle="offcanvas"
                                    data-bs-target="#notificationsSidebar"
                                    aria-controls="notificationsSidebar"
                                    aria-label="Deschide notificările"
                                >
                                    <i class="fa-solid fa-bell"></i>
                                    @if (!empty($notificariTotal))
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">
                                            {{ $notificariTotal }}
                                        </span>
                                    @endif
                                </button>
                            </li>
                            <li class="nav-item dropdown me-3">
                                <a class="nav-link dropdown-toggle rounded-3 {{ request()->routeIs('profile.*') ? 'active culoare2' : 'text-white' }}"
                                    href="#" id="navbarAuthentication" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-user me-1"></i> {{ Auth::user()->name }}
                                </a>

                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarAuthentication">
                                    @if (session()->has('impersonator_id'))
                                        <li>
                                            <span class="dropdown-item-text text-warning">
                                                <i class="fa-solid fa-mask me-1"></i>
                                                Impersonare: {{ Auth::user()->name ?? 'Utilizator necunoscut' }}
                                            </span>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                    @endif
                                    <li>
                                        <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="fa-solid fa-sign-out-alt me-1"></i> Deconectare
                                        </a>
                                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                            @csrf
                                        </form>
                                    </li>
                                    @if (session()->has('impersonator_id'))
                                        <li>
                                            <form method="POST" action="{{ route('tech.impersonation.stop') }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-warning">
                                                    <i class="fa-solid fa-user-shield me-1"></i>
                                                    Revino la {{ session('impersonator_name') ?? 'contul inițial' }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    @else
    @endauth

    @auth
        <div class="offcanvas offcanvas-end" tabindex="-1" id="notificationsSidebar" aria-labelledby="notificationsSidebarLabel">
            <div class="offcanvas-header">
                <div>
                    <div class="small text-muted">Notificări</div>
                    <h5 class="offcanvas-title" id="notificationsSidebarLabel">Urgențe</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Închide"></button>
            </div>
                    <div class="offcanvas-body">
                        @if (empty($notificariTotal))
                            <div class="text-muted">Nu există urgențe pentru moment.</div>
                        @endif
                        <div class="list-group">
                            <a class="list-group-item d-flex justify-content-between align-items-center" href="{{ route('comenzi.index', ['due_soon' => 1]) }}">
                                <span>
                                    <i class="fa-solid fa-hourglass-start me-2 text-primary"></i>
                                    Comenzi cu termen apropiat
                                </span>
                                <span class="badge bg-primary">{{ $notificariComenziSoon }}</span>
                            </a>
                            <a class="list-group-item d-flex justify-content-between align-items-center" href="{{ route('comenzi.index', ['asignate_mie' => 1]) }}">
                                <span>
                                    <i class="fa-solid fa-user-check me-2 text-success"></i>
                                    Comenzi asignate mie
                                </span>
                                <span class="badge bg-success">{{ $notificariComenziAsignateMie }}</span>
                            </a>
                            <a class="list-group-item d-flex justify-content-between align-items-center" href="{{ route('comenzi.index', ['in_asteptare' => 1]) }}">
                                <span>
                                    <i class="fa-solid fa-hourglass-half me-2 text-warning"></i>
                                    Cereri în așteptare
                                </span>
                                <span class="badge bg-warning text-dark">{{ $notificariCereriAsteptareMele }}</span>
                            </a>
                            <a class="list-group-item d-flex justify-content-between align-items-center" href="{{ route('comenzi.index', ['overdue' => 1]) }}">
                                <span>
                                    <i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>
                                    Comenzi întârziate
                                </span>
                                <span class="badge bg-danger">{{ $notificariComenziIntarziate }}</span>
                            </a>
                            <a class="list-group-item d-flex justify-content-between align-items-center" href="{{ route('cereri-oferta') }}">
                                <span>
                                    <i class="fa-solid fa-file-circle-question me-2 text-info"></i>
                                    Cereri ofertă deschise
                                </span>
                                <span class="badge bg-info text-dark">{{ $cereriOfertaDeschise }}</span>
                            </a>
                        </div>
                    </div>
        </div>
    @endauth

    <main class="flex-shrink-0 py-4">
        @yield('content')
    </main>

    <footer class="mt-auto py-2 text-center text-white culoare1">
        <div class="">
            <p class="mb-1">
                © {{ date('Y') }} {{ config('app.name', 'Laravel') }}
            </p>
            <span class="text-white">
                <a href="https://validsoftware.ro/dezvoltare-aplicatii-web-personalizate/" class="text-white" target="_blank">
                    Aplicație web</a>
                dezvoltată de
                <a href="https://validsoftware.ro/" class="text-white" target="_blank">
                    validsoftware.ro
                </a>
            </span>
        </div>
    </footer>
</body>
</html>

