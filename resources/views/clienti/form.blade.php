<div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
    @php
        $currentType = old('type', $client->type ?? 'pf');
        $emailRows = collect(old('emails', isset($client) ? $client->email_addresses : []))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values();

        if ($emailRows->isEmpty() && old('email')) {
            $emailRows = collect([old('email')]);
        }

        if ($emailRows->isEmpty()) {
            $emailRows = collect(['']);
        }
    @endphp
    <div class="col-lg-12 mb-4">
        <label class="mb-0 ps-3">Tip client</label>
        <div class="d-flex gap-4 ps-3 pt-2">
            <div class="form-check">
                <input
                    class="form-check-input"
                    type="radio"
                    name="type"
                    id="type_pf"
                    value="pf"
                    {{ $currentType === 'pf' ? 'checked' : '' }}>
                <label class="form-check-label" for="type_pf">Persoana fizica</label>
            </div>
            <div class="form-check">
                <input
                    class="form-check-input"
                    type="radio"
                    name="type"
                    id="type_pj"
                    value="pj"
                    {{ $currentType === 'pj' ? 'checked' : '' }}>
                <label class="form-check-label" for="type_pj">Persoana juridica</label>
            </div>
        </div>
    </div>
    <div class="col-lg-3 mb-4">
        <label for="nume" class="mb-0 ps-3">Nume<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('nume') ? 'is-invalid' : '' }}"
            name="nume"
            id="nume"
            value="{{ old('nume', $client->nume ?? '') }}"
            required>
    </div>
    <div class="col-lg-3 mb-4">
        <label for="telefon" class="mb-0 ps-3">Telefon</label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('telefon') ? 'is-invalid' : '' }}"
            name="telefon"
            id="telefon"
            value="{{ old('telefon', $client->telefon ?? '') }}">
    </div>
    <div class="col-lg-3 mb-4">
        <label for="telefon_secundar" class="mb-0 ps-3">Telefon secundar</label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('telefon_secundar') ? 'is-invalid' : '' }}"
            name="telefon_secundar"
            id="telefon_secundar"
            value="{{ old('telefon_secundar', $client->telefon_secundar ?? '') }}">
    </div>
    <div class="col-lg-12 mb-4">
        <label class="mb-0 ps-3">Emailuri</label>
        <div class="ps-3 pt-2" data-client-email-list data-next-index="{{ $emailRows->count() }}">
            @foreach ($emailRows as $index => $emailValue)
                <div class="input-group mb-2" data-client-email-row>
                    <input
                        type="email"
                        class="form-control bg-white rounded-3 {{ $errors->has('emails.' . $index) ? 'is-invalid' : '' }}"
                        name="emails[]"
                        value="{{ $emailValue }}"
                        placeholder="email@client.ro">
                    <button type="button" class="btn btn-outline-danger rounded-3 ms-2" data-client-email-remove {{ $emailRows->count() === 1 ? 'disabled' : '' }}>
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            @endforeach

            @if ($errors->has('emails') || $errors->has('email'))
                <div class="small text-danger mb-2">{{ $errors->first('emails') ?: $errors->first('email') }}</div>
            @endif

            <button type="button" class="btn btn-sm btn-outline-primary rounded-3" data-client-email-add>
                <i class="fa-solid fa-plus me-1"></i> Adauga email
            </button>
        </div>
    </div>
    <div class="col-lg-12 mb-4">
        <label for="adresa" class="mb-0 ps-3">Adresa</label>
        <textarea
            class="form-control bg-white rounded-3 {{ $errors->has('adresa') ? 'is-invalid' : '' }}"
            name="adresa"
            id="adresa"
            rows="3">{{ old('adresa', $client->adresa ?? '') }}</textarea>
    </div>

    <div class="col-lg-12">
        <div id="client-fields-pf" class="row">
            <div class="col-lg-3 mb-4">
                <label for="cnp" class="mb-0 ps-3">CNP</label>
                <input
                    type="text"
                    class="form-control bg-white rounded-3 {{ $errors->has('cnp') ? 'is-invalid' : '' }}"
                    name="cnp"
                    id="cnp"
                    value="{{ old('cnp', $client->cnp ?? '') }}">
            </div>
            <div class="col-lg-3 mb-4">
                <label for="sex" class="mb-0 ps-3">Sex</label>
                <select
                    class="form-select bg-white rounded-3 {{ $errors->has('sex') ? 'is-invalid' : '' }}"
                    name="sex"
                    id="sex">
                    <option value="">Selecteaza</option>
                    <option value="M" {{ old('sex', $client->sex ?? '') === 'M' ? 'selected' : '' }}>M</option>
                    <option value="F" {{ old('sex', $client->sex ?? '') === 'F' ? 'selected' : '' }}>F</option>
                </select>
            </div>
        </div>

        <div id="client-fields-pj" class="row">
            <div class="col-lg-3 mb-4">
                <label for="reg_com" class="mb-0 ps-3">Nr. Reg. com.</label>
                <input
                    type="text"
                    class="form-control bg-white rounded-3 {{ $errors->has('reg_com') ? 'is-invalid' : '' }}"
                    name="reg_com"
                    id="reg_com"
                    value="{{ old('reg_com', $client->reg_com ?? '') }}">
            </div>
            <div class="col-lg-3 mb-4">
                <label for="cui" class="mb-0 ps-3">CUI</label>
                <input
                    type="text"
                    class="form-control bg-white rounded-3 {{ $errors->has('cui') ? 'is-invalid' : '' }}"
                    name="cui"
                    id="cui"
                    value="{{ old('cui', $client->cui ?? '') }}">
            </div>
            <div class="col-lg-3 mb-4">
                <label for="iban" class="mb-0 ps-3">IBAN</label>
                <input
                    type="text"
                    class="form-control bg-white rounded-3 {{ $errors->has('iban') ? 'is-invalid' : '' }}"
                    name="iban"
                    id="iban"
                    value="{{ old('iban', $client->iban ?? '') }}">
            </div>
            <div class="col-lg-3 mb-4">
                <label for="banca" class="mb-0 ps-3">Banca</label>
                <input
                    type="text"
                    class="form-control bg-white rounded-3 {{ $errors->has('banca') ? 'is-invalid' : '' }}"
                    name="banca"
                    id="banca"
                    value="{{ old('banca', $client->banca ?? '') }}">
            </div>
            <div class="col-lg-3 mb-4">
                <label for="reprezentant" class="mb-0 ps-3">Reprezentant</label>
                <input
                    type="text"
                    class="form-control bg-white rounded-3 {{ $errors->has('reprezentant') ? 'is-invalid' : '' }}"
                    name="reprezentant"
                    id="reprezentant"
                    value="{{ old('reprezentant', $client->reprezentant ?? '') }}">
            </div>
            <div class="col-lg-3 mb-4">
                <label for="reprezentant_functie" class="mb-0 ps-3">Reprezentant functie</label>
                <input
                    type="text"
                    class="form-control bg-white rounded-3 {{ $errors->has('reprezentant_functie') ? 'is-invalid' : '' }}"
                    name="reprezentant_functie"
                    id="reprezentant_functie"
                    value="{{ old('reprezentant_functie', $client->reprezentant_functie ?? '') }}">
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12 mb-2 d-flex justify-content-center">
        @if ($canWriteClienti ?? false)
            <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
                <i class="fa-solid fa-save me-1"></i> {{ $buttonText }}
            </button>
        @endif
        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('clienti.index')) }}">
            Renunta
        </a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const pf = document.getElementById('type_pf');
        const pj = document.getElementById('type_pj');
        const pfFields = document.getElementById('client-fields-pf');
        const pjFields = document.getElementById('client-fields-pj');
        const emailList = document.querySelector('[data-client-email-list]');

        const applyVisibility = () => {
            const type = (pf?.checked ? 'pf' : (pj?.checked ? 'pj' : 'pf'));
            pfFields?.classList.toggle('d-none', type !== 'pf');
            pjFields?.classList.toggle('d-none', type !== 'pj');
        };

        const syncEmailRemoveButtons = () => {
            if (!emailList) {
                return;
            }

            const rows = Array.from(emailList.querySelectorAll('[data-client-email-row]'));
            rows.forEach((row) => {
                const removeBtn = row.querySelector('[data-client-email-remove]');
                if (removeBtn) {
                    removeBtn.disabled = rows.length === 1;
                }
            });
        };

        const buildEmailRow = () => {
            const row = document.createElement('div');
            row.className = 'input-group mb-2';
            row.setAttribute('data-client-email-row', '');
            row.innerHTML = `
                <input
                    type="email"
                    class="form-control bg-white rounded-3"
                    name="emails[]"
                    placeholder="email@client.ro">
                <button type="button" class="btn btn-outline-danger rounded-3 ms-2" data-client-email-remove>
                    <i class="fa-solid fa-trash"></i>
                </button>
            `;

            return row;
        };

        emailList?.addEventListener('click', (event) => {
            const addButton = event.target.closest('[data-client-email-add]');
            if (addButton) {
                addButton.insertAdjacentElement('beforebegin', buildEmailRow());
                syncEmailRemoveButtons();
                return;
            }

            const removeButton = event.target.closest('[data-client-email-remove]');
            if (!removeButton) {
                return;
            }

            const rows = emailList.querySelectorAll('[data-client-email-row]');
            if (rows.length <= 1) {
                return;
            }

            removeButton.closest('[data-client-email-row]')?.remove();
            syncEmailRemoveButtons();
        });

        pf?.addEventListener('change', applyVisibility);
        pj?.addEventListener('change', applyVisibility);
        applyVisibility();
        syncEmailRemoveButtons();
    });
</script>
