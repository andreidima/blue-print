<div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
    <div class="col-lg-6 mb-4">
        <label for="denumire" class="mb-0 ps-3">Denumire<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('denumire') ? 'is-invalid' : '' }}"
            name="denumire"
            id="denumire"
            value="{{ old('denumire', $material->denumire ?? '') }}"
            required
        >
    </div>
    <div class="col-lg-3 mb-4">
        <label for="unitate_masura" class="mb-0 ps-3">UM<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('unitate_masura') ? 'is-invalid' : '' }}"
            name="unitate_masura"
            id="unitate_masura"
            value="{{ old('unitate_masura', $material->unitate_masura ?? '') }}"
            placeholder="buc, mp, rola"
            required
        >
    </div>
    <div class="col-lg-3 mb-4">
        <div class="form-check mt-4 ps-4">
            <input class="form-check-input" type="checkbox" name="activ" id="activ" value="1"
                {{ old('activ', $material->activ ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="activ">Activ</label>
        </div>
    </div>
    <div class="col-lg-12 mb-4">
        <label for="descriere" class="mb-0 ps-3">Descriere</label>
        <textarea
            class="form-control bg-white rounded-3 {{ $errors->has('descriere') ? 'is-invalid' : '' }}"
            name="descriere"
            id="descriere"
            rows="3"
            placeholder="Observatii optionale despre material"
        >{{ old('descriere', $material->descriere ?? '') }}</textarea>
    </div>
</div>

<div class="row">
    <div class="col-lg-12 mb-2 d-flex justify-content-center">
        @if ($canWriteProduse ?? false)
            <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
                <i class="fa-solid fa-save me-1"></i> {{ $buttonText }}
            </button>
        @endif
        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('materiale.index')) }}">
            Renunta
        </a>
    </div>
</div>
