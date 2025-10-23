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
                        <li class="nav-item me-3">
                            <a class="nav-link active" aria-current="page" href="{{ route('produse.index') }}">
                                <i class="fa-solid fa-box me-1"></i> Produse
                            </a>
                        </li>
                        @php
                            $ordersActive = request()->routeIs('comenzi.iesiri.*') || request()->routeIs('woocommerce.orders.*');
                        @endphp
                        <li class="nav-item me-3 dropdown">
                            <a class="nav-link dropdown-toggle {{ $ordersActive ? 'active' : '' }}" href="#" id="ordersDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-list-check me-1"></i> Comenzi
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="ordersDropdown">
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('comenzi.iesiri.*') ? 'active' : '' }}" href="{{ route('comenzi.iesiri.index') }}">
                                        <i class="fa-solid fa-dolly me-1"></i> Comenzi ieșiri
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('woocommerce.orders.*') ? 'active' : '' }}" href="{{ route('woocommerce.orders.index') }}">
                                        <i class="fa-solid fa-store me-1"></i> Comenzi site
                                    </a>
                                </li>
                            </ul>
                        </li>
                        @php
                            $procurementActive = request()->routeIs('procurement.purchase-orders.*');
                        @endphp
                        <li class="nav-item me-3">
                            <a class="nav-link {{ $procurementActive ? 'active' : '' }}" href="{{ route('procurement.purchase-orders.index') }}">
                                <i class="fa-solid fa-clipboard-list me-1"></i> Achiziții
                            </a>
                        </li>
                        <li class="nav-item me-3">
                            <a class="nav-link active" aria-current="page" href="{{ route('miscari.intrari') }}">
                                <i class="fa-solid fa-plus-circle me-1"></i> Intrări
                            </a>
                        </li>
                        <li class="nav-item me-3">
                            <a class="nav-link active" aria-current="page" href="{{ route('miscari.iesiri') }}">
                                <i class="fa-solid fa-minus-circle me-1"></i> Ieșiri
                            </a>
                        </li>
                        <li class="nav-item me-3 dropdown">
                            <a class="nav-link active dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-cubes"></i> Resurse
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li>
                                    <a class="dropdown-item" href="{{ route('categorii.index') }}">
                                        <i class="fa-solid fa-tags"></i> Categorii produse
                                    </a>
                                </li>
                                @can('admin-action')
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('users.index') }}">
                                            <i class="fa-solid fa-users"></i> Utilizatori
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        @can('super-admin-action')
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
                                </ul>
                            </li>
                        @endcan
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        @if (session()->has('impersonator_id'))
                            <li class="nav-item me-3 align-self-center">
                                <span class="badge bg-warning text-dark">
                                    <i class="fa-solid fa-mask me-1"></i>
                                    Impersonare: {{ Auth::user()->name ?? 'Utilizator necunoscut' }}
                                </span>
                            </li>
                        @endif
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
                            <li class="nav-item dropdown me-3">
                                <a class="nav-link dropdown-toggle rounded-3 {{ request()->routeIs('profile.*') ? 'active culoare2' : 'text-white' }}"
                                    href="#" id="navbarAuthentication" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-user me-1"></i> {{ Auth::user()->name }}
                                </a>

                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarAuthentication">
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
                                        <li><hr class="dropdown-divider"></li>
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
