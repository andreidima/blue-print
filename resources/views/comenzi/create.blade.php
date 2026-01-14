@extends ('layouts.app')

@section('content')
@php
    $produsIds = old('produs_id', [null]);
    $cantitati = old('cantitate', [1]);
    $rows = max(count($produsIds ?? []), count($cantitati ?? []), 1);
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-6">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-clipboard-list me-1"></i> Adauga comanda
            </span>
        </div>
        <div class="col-lg-6 text-end">
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        <form method="POST" action="{{ route('comenzi.store') }}">
            @csrf
            <div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
                <div class="col-lg-6 mb-3">
                    <label for="client_id" class="mb-0 ps-3">Client<span class="text-danger">*</span></label>
                    <select class="form-select bg-white rounded-3 {{ $errors->has('client_id') ? 'is-invalid' : '' }}" name="client_id" id="client_id" required>
                        <option value="">Selecteaza client</option>
                        @foreach ($clienti as $client)
                            <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->nume_complet }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="tip" class="mb-0 ps-3">Tip<span class="text-danger">*</span></label>
                    <select class="form-select bg-white rounded-3 {{ $errors->has('tip') ? 'is-invalid' : '' }}" name="tip" id="tip" required>
                        @foreach ($tipuri as $key => $label)
                            <option value="{{ $key }}" {{ old('tip', 'comanda_ferma') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="sursa" class="mb-0 ps-3">Sursa<span class="text-danger">*</span></label>
                    <select class="form-select bg-white rounded-3 {{ $errors->has('sursa') ? 'is-invalid' : '' }}" name="sursa" id="sursa" required>
                        @foreach ($surse as $key => $label)
                            <option value="{{ $key }}" {{ old('sursa', 'fizic') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="status" class="mb-0 ps-3">Status<span class="text-danger">*</span></label>
                    <select class="form-select bg-white rounded-3 {{ $errors->has('status') ? 'is-invalid' : '' }}" name="status" id="status" required>
                        @foreach ($statusuri as $key => $label)
                            <option value="{{ $key }}" {{ old('status', 'nou') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="timp_estimat_livrare" class="mb-0 ps-3">Timp estimat livrare<span class="text-danger">*</span></label>
                    <input
                        type="datetime-local"
                        class="form-control bg-white rounded-3 {{ $errors->has('timp_estimat_livrare') ? 'is-invalid' : '' }}"
                        name="timp_estimat_livrare"
                        id="timp_estimat_livrare"
                        value="{{ old('timp_estimat_livrare', now()->addDay()->format('Y-m-d\\TH:i')) }}"
                        required>
                </div>
                <div class="col-lg-3 mb-3 d-flex align-items-center">
                    <div class="form-check mt-4 ps-4">
                        <input class="form-check-input" type="checkbox" name="necesita_tipar_exemplu" id="necesita_tipar_exemplu" value="1"
                            {{ old('necesita_tipar_exemplu') ? 'checked' : '' }}>
                        <label class="form-check-label" for="necesita_tipar_exemplu">Necesita tipar exemplu</label>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-12">
                    <h6 class="mb-3">Produse</h6>
                    <div class="table-responsive rounded">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Produs</th>
                                    <th width="15%">Cantitate</th>
                                    <th width="10%"></th>
                                </tr>
                            </thead>
                            <tbody id="linii-produse">
                                @for ($i = 0; $i < $rows; $i++)
                                    <tr>
                                        <td>
                                            <select class="form-select form-select-sm" name="produs_id[]">
                                                <option value="">Selecteaza produs</option>
                                                @foreach ($produse as $produs)
                                                    <option value="{{ $produs->id }}" {{ (string)($produsIds[$i] ?? '') === (string)$produs->id ? 'selected' : '' }}>
                                                        {{ $produs->denumire }} ({{ number_format($produs->pret, 2) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" min="1" class="form-control form-control-sm" name="cantitate[]" value="{{ $cantitati[$i] ?? 1 }}">
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-line">Sterge</button>
                                        </td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-line">
                        <i class="fa-solid fa-plus me-1"></i> Adauga linie
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12 mb-2 d-flex justify-content-center">
                    <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
                        <i class="fa-solid fa-save me-1"></i> Salveaza comanda
                    </button>
                    <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
                        Renunta
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const addLineButton = document.getElementById('add-line');
        const tbody = document.getElementById('linii-produse');

        const bindRemove = (row) => {
            const removeButton = row.querySelector('.remove-line');
            if (!removeButton) {
                return;
            }

            removeButton.addEventListener('click', () => {
                if (tbody.querySelectorAll('tr').length > 1) {
                    row.remove();
                }
            });
        };

        tbody.querySelectorAll('tr').forEach((row) => bindRemove(row));

        addLineButton?.addEventListener('click', () => {
            const templateRow = tbody.querySelector('tr');
            if (!templateRow) {
                return;
            }

            const newRow = templateRow.cloneNode(true);
            newRow.querySelectorAll('select, input').forEach((input) => {
                if (input.tagName === 'SELECT') {
                    input.value = '';
                } else if (input.name === 'cantitate[]') {
                    input.value = 1;
                } else {
                    input.value = '';
                }
            });
            tbody.appendChild(newRow);
            bindRemove(newRow);
        });
    });
</script>
@endsection
