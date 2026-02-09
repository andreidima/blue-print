@extends ('layouts.app')

@section('content')
@php
    $canWriteComenzi = auth()->user()?->hasPermission('comenzi.write') ?? false;
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
            <fieldset {{ $canWriteComenzi ? '' : 'disabled' }}>
            <div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
                <div class="col-lg-6 mb-3">
                    <label class="mb-0 ps-3">Client<span class="text-danger">*</span></label>
                    <div
                        class="js-client-selector"
                        data-name="client_id"
                        data-search-url="{{ route('clienti.select-options') }}"
                        data-store-url="{{ route('clienti.quick-store') }}"
                        data-initial-client-id="{{ old('client_id') }}"
                        data-invalid="{{ $errors->has('client_id') ? '1' : '0' }}"
                    ></div>
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
                    <label for="data_solicitarii" class="mb-0 ps-3">Data solicitarii<span class="text-danger">*</span></label>
                    <input
                        type="date"
                        class="form-control bg-white rounded-3 {{ $errors->has('data_solicitarii') ? 'is-invalid' : '' }}"
                        name="data_solicitarii"
                        id="data_solicitarii"
                        value="{{ old('data_solicitarii', now()->format('Y-m-d')) }}"
                        required>
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
                        <input class="form-check-input" type="checkbox" name="necesita_mockup" id="necesita_mockup" value="1"
                            {{ old('necesita_mockup') ? 'checked' : '' }}>
                        <label class="form-check-label" for="necesita_mockup">Necesita mockup</label>
                    </div>
                </div>
                <div class="col-lg-3 mb-3 d-flex align-items-center">
                    <div class="form-check mt-4 ps-4">
                        <input class="form-check-input" type="checkbox" name="necesita_tipar_exemplu" id="necesita_tipar_exemplu" value="1"
                            {{ old('necesita_tipar_exemplu') ? 'checked' : '' }}>
                        <label class="form-check-label" for="necesita_tipar_exemplu">Necesita tipar exemplu</label>
                    </div>
                </div>
            </div>

            @php
                $oldSolicitari = old('solicitari', [['solicitare_client' => '', 'cantitate' => '']]);
                if (!is_array($oldSolicitari) || empty($oldSolicitari)) {
                    $oldSolicitari = [['solicitare_client' => '', 'cantitate' => '']];
                }
            @endphp
            <div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
                <div class="col-lg-12 mb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h6 class="mb-1 ps-3">Informatii comanda</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" data-solicitare-add>
                        <i class="fa-solid fa-plus me-1"></i> Adauga solicitare
                    </button>
                </div>
                <div class="col-lg-12" data-solicitari-list>
                    @foreach ($oldSolicitari as $index => $entry)
                        <div class="row g-3 align-items-end mb-2" data-solicitare-row>
                            <div class="col-lg-8">
                                <label class="mb-0 ps-3">Solicitare client</label>
                                <textarea
                                    class="form-control bg-white rounded-3"
                                    name="solicitari[{{ $index }}][solicitare_client]"
                                    rows="3"
                                >{{ $entry['solicitare_client'] ?? '' }}</textarea>
                            </div>
                            <div class="col-lg-3">
                                <label class="mb-0 ps-3">Cantitate</label>
                                <input
                                    type="number"
                                    min="1"
                                    class="form-control bg-white rounded-3"
                                    name="solicitari[{{ $index }}][cantitate]"
                                    value="{{ $entry['cantitate'] ?? '' }}"
                                >
                            </div>
                            <div class="col-lg-1 text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm w-100" data-solicitare-remove>
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12 mb-2 d-flex justify-content-center">
                    @if ($canWriteComenzi)
                        <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
                            <i class="fa-solid fa-save me-1"></i> Salveaza comanda
                        </button>
                    @endif
                    <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
                        Renunta
                    </a>
                </div>
            </div>
            </fieldset>
        </form>
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const list = document.querySelector('[data-solicitari-list]');
        const addButton = document.querySelector('[data-solicitare-add]');
        if (!list || !addButton) return;

        const buildRow = (index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'row g-3 align-items-end mb-2';
            wrapper.setAttribute('data-solicitare-row', '');
            wrapper.innerHTML = `
                <div class="col-lg-8">
                    <label class="mb-0 ps-3">Solicitare client</label>
                    <textarea class="form-control bg-white rounded-3" name="solicitari[${index}][solicitare_client]" rows="3"></textarea>
                </div>
                <div class="col-lg-3">
                    <label class="mb-0 ps-3">Cantitate</label>
                    <input type="number" min="1" class="form-control bg-white rounded-3" name="solicitari[${index}][cantitate]">
                </div>
                <div class="col-lg-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-solicitare-remove>
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `;
            return wrapper;
        };

        const renumberRows = () => {
            const rows = Array.from(list.querySelectorAll('[data-solicitare-row]'));
            rows.forEach((row, index) => {
                const textarea = row.querySelector('textarea');
                const input = row.querySelector('input[type="number"]');
                if (textarea) textarea.name = `solicitari[${index}][solicitare_client]`;
                if (input) input.name = `solicitari[${index}][cantitate]`;
            });
        };

        const handleRemove = (event) => {
            const target = event.target.closest('[data-solicitare-remove]');
            if (!target) return;
            const row = target.closest('[data-solicitare-row]');
            if (!row) return;
            row.remove();
            if (list.querySelectorAll('[data-solicitare-row]').length === 0) {
                list.appendChild(buildRow(0));
            }
            renumberRows();
        };

        addButton.addEventListener('click', () => {
            const nextIndex = list.querySelectorAll('[data-solicitare-row]').length;
            list.appendChild(buildRow(nextIndex));
        });
        list.addEventListener('click', handleRemove);
    });
</script>

@endsection
