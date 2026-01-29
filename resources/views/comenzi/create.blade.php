@extends ('layouts.app')

@section('content')
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

            <div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
                <div class="col-lg-12 mb-2">
                    <h6 class="mb-1 ps-3">Informatii comanda</h6>
                </div>
                <div class="col-lg-8 mb-3">
                    <label for="solicitare_client" class="mb-0 ps-3">Solicitare client</label>
                    <textarea
                        class="form-control bg-white rounded-3 {{ $errors->has('solicitare_client') ? 'is-invalid' : '' }}"
                        name="solicitare_client"
                        id="solicitare_client"
                        rows="4"
                    >{{ old('solicitare_client') }}</textarea>
                </div>
                <div class="col-lg-4 mb-3">
                    <label for="cantitate_comanda" class="mb-0 ps-3">Cantitate</label>
                    <input
                        type="number"
                        min="1"
                        class="form-control bg-white rounded-3 {{ $errors->has('cantitate_comanda') ? 'is-invalid' : '' }}"
                        name="cantitate_comanda"
                        id="cantitate_comanda"
                        value="{{ old('cantitate_comanda') }}"
                    >
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

@endsection
