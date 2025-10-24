<div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
    <div class="col-lg-6 mb-4">
        <label for="categorie_id" class="mb-0 ps-3">Categorie<span class="text-danger">*</span></label>
        <select
            name="categorie_id"
            id="categorie_id"
            class="form-control bg-white rounded-3 {{ $errors->has('categorie_id') ? 'is-invalid' : '' }}"
            required
        >
            <option value="" disabled {{ old('categorie_id', $produs->categorie_id ?? '') ? '' : 'selected' }}>Alege categorie…</option>
            @foreach($allCategorii as $cat)
                <option value="{{ $cat->id }}" {{ old('categorie_id', $produs->categorie_id ?? '') == $cat->id ? 'selected' : '' }}>
                    {{ $cat->nume }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-lg-6 mb-4">
        <label for="nume" class="mb-0 ps-3">Nume<span class="text-danger">*</span></label>
        <input
            type="text"
            name="nume"
            id="nume"
            class="form-control bg-white rounded-3 {{ $errors->has('nume') ? 'is-invalid' : '' }}"
            value="{{ old('nume', $produs->nume ?? '') }}"
            required
        >
    </div>

    <div class="col-lg-6 mb-4">
        <label for="sku" class="mb-0 ps-3">SKU</label>
        <input
            type="text"
            name="sku"
            id="sku"
            class="form-control bg-white rounded-3 {{ $errors->has('sku') ? 'is-invalid' : '' }}"
            value="{{ old('sku', $produs->sku ?? '') }}"
            maxlength="100"
        >
    </div>

    <div class="col-lg-6 mb-4">
        <label for="sku_aliases" class="mb-0 ps-3">SKU-uri suplimentare</label>
        @php
            $aliasInput = old('sku_aliases');
            if (is_array($aliasInput)) {
                $aliasInput = implode("\n", $aliasInput);
            } elseif (! is_string($aliasInput)) {
                $aliasInput = isset($produs)
                    ? $produs->skuAliases->pluck('sku')->implode("\n")
                    : '';
            }

            $aliasErrors = collect($errors->get('sku_aliases'))
                ->merge(collect($errors->get('sku_aliases.*'))->flatten())
                ->filter();
        @endphp
        <textarea
            name="sku_aliases"
            id="sku_aliases"
            class="form-control bg-white rounded-3 {{ $aliasErrors->isNotEmpty() ? 'is-invalid' : '' }}"
            rows="3"
            placeholder="Introduceți câte un SKU pe linie"
        >{{ $aliasInput }}</textarea>
        <small class="ps-3 text-muted">Câte un SKU pe linie. Aliasurile nu pot fi folosite de alte produse.</small>
        @if($aliasErrors->isNotEmpty())
            <div class="invalid-feedback d-block">
                @foreach($aliasErrors as $aliasError)
                    <div>{{ $aliasError }}</div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="col-lg-4 mb-4">
        <label for="cantitate" class="mb-0 ps-3">Cantitate</label>
        <input
            type="number"
            name="cantitate"
            id="cantitate"
            class="form-control bg-white rounded-3 {{ $errors->has('cantitate') ? 'is-invalid' : '' }}"
            value="{{ old('cantitate', $produs->cantitate ?? '') }}"
            min="0"
        >
    </div>

    <div class="col-lg-4 mb-4">
        <label for="prag_minim" class="mb-0 ps-3">Prag minim stoc</label>
        <input
            type="number"
            name="prag_minim"
            id="prag_minim"
            class="form-control bg-white rounded-3 {{ $errors->has('prag_minim') ? 'is-invalid' : '' }}"
            value="{{ old('prag_minim', $produs->prag_minim ?? '') }}"
            min="0"
        >
    </div>

    <div class="col-lg-4 mb-4 text-center" id="datePicker">
        <label for="data_procesare" class="mb-0 ps-0">Data procesare</label>
        <vue-datepicker-next
            id="data_procesare"
            data-veche="{{ old('data_procesare', $produs->data_procesare ?? null) }}"
            nume-camp-db="data_procesare"
            tip="date"
            value-type="YYYY-MM-DD"
            format="DD.MM.YYYY"
            :latime="{ width: '125px' }"
        ></vue-datepicker-next>
    </div>

    <div class="col-lg-4 mb-4">
        <label for="pret" class="mb-0 ps-3">Preț</label>
        <input
            type="text"
            name="pret"
            id="pret"
            class="form-control bg-white rounded-3 {{ $errors->has('pret') ? 'is-invalid' : '' }}"
            value="{{ old('pret', $produs->pret ?? '') }}"
        >
        <small class="ps-3">Punct(.) pentru zecimale</small>
    </div>

    <div class="col-lg-4 mb-4">
        <label for="lungime" class="mb-0 ps-3">Lungime</label>
        <input
            type="text"
            name="lungime"
            id="lungime"
            class="form-control bg-white rounded-3 {{ $errors->has('lungime') ? 'is-invalid' : '' }}"
            value="{{ old('lungime', $produs->lungime ?? '') }}"
        >
        <small class="ps-3">Punct(.) pentru zecimale</small>
    </div>

    <div class="col-lg-4 mb-4">
        <label for="latime" class="mb-0 ps-3">Lățime</label>
        <input
            type="text"
            name="latime"
            id="latime"
            class="form-control bg-white rounded-3 {{ $errors->has('latime') ? 'is-invalid' : '' }}"
            value="{{ old('latime', $produs->latime ?? '') }}"
        >
        <small class="ps-3">Punct(.) pentru zecimale</small>
    </div>

    <div class="col-lg-4 mb-4">
        <label for="grosime" class="mb-0 ps-3">Grosime</label>
        <input
            type="text"
            name="grosime"
            id="grosime"
            class="form-control bg-white rounded-3 {{ $errors->has('grosime') ? 'is-invalid' : '' }}"
            value="{{ old('grosime', $produs->grosime ?? '') }}"
        >
        <small class="ps-3">Punct(.) pentru zecimale</small>
    </div>

    <div class="col-lg-12 mb-4">
        <label for="observatii" class="mb-0 ps-3">Observații</label>
        <textarea
            name="observatii"
            id="observatii"
            class="form-control bg-white rounded-3 {{ $errors->has('observatii') ? 'is-invalid' : '' }}"
            rows="4"
        >{{ old('observatii', $produs->observatii ?? '') }}</textarea>
    </div>
</div>

<div class="row">
    <div class="col-lg-12 mb-2 d-flex justify-content-center">
        <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
            <i class="fa-solid fa-save me-1"></i> {{ $buttonText }}
        </button>
        <a
            href="{{ Session::get('returnUrl', route('produse.index')) }}"
            class="btn btn-secondary rounded-3"
        >
            <i class="fa-solid fa-arrow-left me-1"></i> Renunță
        </a>
    </div>
</div>
