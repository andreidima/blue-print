<div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
    <div class="col-lg-8 mb-4">
        <label for="denumire" class="mb-0 ps-3">Denumire<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('denumire') ? 'is-invalid' : '' }}"
            name="denumire"
            id="denumire"
            value="{{ old('denumire', $nomenclator->denumire ?? '') }}"
            required>
    </div>
    <div class="col-lg-4 mb-4">
        <label for="pret" class="mb-0 ps-3">Pret</label>
        <input
            type="number"
            step="0.01"
            min="0"
            class="form-control bg-white rounded-3 {{ $errors->has('pret') ? 'is-invalid' : '' }}"
            name="pret"
            id="pret"
            value="{{ old('pret', isset($nomenclator) && $nomenclator->pret !== null ? number_format((float) $nomenclator->pret, 2, '.', '') : '') }}">
    </div>
    <div class="col-lg-12 mb-4">
        <label for="descriere" class="mb-0 ps-3">Descriere</label>
        <textarea
            class="form-control bg-white rounded-3 {{ $errors->has('descriere') ? 'is-invalid' : '' }}"
            name="descriere"
            id="descriere"
            rows="3"
            placeholder="Ex: model 2026"
        >{{ old('descriere', $nomenclator->descriere ?? '') }}</textarea>
        @if ($errors->has('descriere'))
            <div class="invalid-feedback d-block">
                {{ $errors->first('descriere') }}
            </div>
        @endif
    </div>
    @if ($errors->has('pret'))
        <div class="col-lg-12">
            <div class="invalid-feedback d-block">
                {{ $errors->first('pret') }}
            </div>
        </div>
    @endif
</div>

<div class="row">
    <div class="col-lg-12 mb-2 d-flex justify-content-center">
        @if ($canWriteProduse ?? false)
            <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
                <i class="fa-solid fa-save me-1"></i> {{ $buttonText }}
            </button>
        @endif
        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('nomenclator.index')) }}">
            Renunta
        </a>
    </div>
</div>
