@extends ('layouts.app')

@section('content')
@php
    $title = $pageTitle ?? 'Comenzi';
    $statusPlataOptions = \App\Enums\StatusPlata::options();
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-clipboard-list"></i> {{ $title }}
            </span>
        </div>

        <div class="col-lg-6">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-4">
                        @if ($fixedTip)
                            <input type="hidden" name="tip" value="{{ $fixedTip }}">
                            <span class="badge bg-secondary w-100 text-start">Tip: {{ $tipuri[$fixedTip] ?? $fixedTip }}</span>
                        @else
                            <select class="form-select rounded-3" name="tip">
                                <option value="">Tip</option>
                                @foreach ($tipuri as $key => $label)
                                    <option value="{{ $key }}" {{ $tip === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="col-lg-4">
                        <select class="form-select rounded-3" name="status">
                            <option value="">Status</option>
                            @foreach ($statusuri as $key => $label)
                                <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <select class="form-select rounded-3" name="sursa">
                            <option value="">Sursa</option>
                            @foreach ($surse as $key => $label)
                                <option value="{{ $key }}" {{ $sursa === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-6">
                        <input type="text" class="form-control rounded-3" id="client" name="client" placeholder="Client: nume/telefon/email" value="{{ $client }}">
                    </div>
                    <div class="col-lg-3">
                        <input type="date" class="form-control rounded-3" id="timp_de" name="timp_de" value="{{ $dataDe }}">
                    </div>
                    <div class="col-lg-3">
                        <input type="date" class="form-control rounded-3" id="timp_pana" name="timp_pana" value="{{ $dataPana }}">
                    </div>
                </div>
                <div class="row custom-search-form justify-content-center align-items-center">
                    <div class="col-lg-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="overdue" id="overdue" value="1" {{ $overdue ? 'checked' : '' }}>
                            <label class="form-check-label" for="overdue">Intarziate</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="asignate_mie" id="asignate_mie" value="1" {{ $asignateMie ? 'checked' : '' }}>
                            <label class="form-check-label" for="asignate_mie">Asignate mie</label>
                        </div>
                    </div>
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
            <a class="btn btn-sm btn-success text-white border border-dark rounded-3 col-md-8" href="{{ route('comenzi.create') }}" role="button">
                <i class="fas fa-plus text-white me-1"></i> Adauga comanda
            </a>
        </div>
    </div>

    <div class="card-body px-0 py-3">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Comenzi table">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2 text-nowrap" width="5%"><i class="fa-solid fa-hashtag"></i></th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="25%"><i class="fa-solid fa-user me-1"></i> Client</th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%"><i class="fa-solid fa-tag me-1"></i> Tip</th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="15%"><i class="fa-solid fa-list-check me-1"></i> Status</th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%"><i class="fa-solid fa-circle-up me-1"></i> Sursa</th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="15%"><i class="fa-solid fa-clock me-1"></i> Livrare</th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%"><i class="fa-solid fa-money-bill me-1"></i> Total</th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%"><i class="fa-solid fa-credit-card me-1"></i> Plata</th>
                        <th scope="col" class="text-white culoare2 text-nowrap text-end" width="10%"><i class="fa-solid fa-cogs me-1"></i> Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($comenzi as $comanda)
                        @php
                            $rowClass = '';
                            if ($comanda->is_overdue) {
                                $rowClass = 'bg-danger';
                            } elseif ($comanda->is_due_soon) {
                                $rowClass = 'bg-warning';
                            }
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td>
                                {{ ($comenzi->currentpage()-1) * $comenzi->perpage() + $loop->index + 1 }}
                            </td>
                            <td>
                                <div>{{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                                <div class="small text-muted">{{ optional($comanda->client)->telefon }}</div>
                            </td>
                            <td>
                                {{ $tipuri[$comanda->tip] ?? $comanda->tip }}
                            </td>
                            <td>
                                <div>{{ $statusuri[$comanda->status] ?? $comanda->status }}</div>
                                @if ($comanda->is_overdue)
                                    <span class="badge bg-danger">Intarziata</span>
                                @elseif ($comanda->is_due_soon)
                                    <span class="badge bg-warning text-dark">In urmatoarele 24h</span>
                                @endif
                            </td>
                            <td>
                                {{ $surse[$comanda->sursa] ?? $comanda->sursa }}
                            </td>
                            <td>
                                {{ optional($comanda->timp_estimat_livrare)->format('d.m.Y H:i') }}
                            </td>
                            <td>
                                {{ number_format($comanda->total, 2) }}
                            </td>
                            <td>
                                {{ $statusPlataOptions[$comanda->status_plata] ?? $comanda->status_plata }}
                            </td>
                            <td>
                                <div class="d-flex justify-content-end py-0">
                                    <a href="{{ route('comenzi.show', $comanda) }}" class="flex me-1" aria-label="Vezi comanda {{ $comanda->id }}">
                                        <span class="badge bg-success"><i class="fa-solid fa-eye"></i></span>
                                    </a>
                                    <form method="POST" action="{{ route('comenzi.destroy', $comanda) }}" onsubmit="return confirm('Sigur vrei sa stergi aceasta comanda?')">
                                        @method('DELETE')
                                        @csrf
                                        <button type="submit" class="badge bg-danger border-0" aria-label="Sterge comanda {{ $comanda->id }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fa-solid fa-clipboard-list fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu s-au gasit comenzi in baza de date.</p>
                                @if($client || $status || $sursa || $tip || $dataDe || $dataPana || $overdue || $asignateMie)
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
                {{ $comenzi->appends(Request::except('page'))->links() }}
            </ul>
        </nav>
    </div>
</div>
@endsection
