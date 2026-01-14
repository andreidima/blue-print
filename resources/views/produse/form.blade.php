<div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
    <div class="col-lg-8 mb-4">
        <label for="denumire" class="mb-0 ps-3">Denumire<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('denumire') ? 'is-invalid' : '' }}"
            name="denumire"
            id="denumire"
            value="{{ old('denumire', $produs->denumire ?? '') }}"
            required>
    </div>
    <div class="col-lg-4 mb-4">
        <label for="pret" class="mb-0 ps-3">Pret<span class="text-danger">*</span></label>
        <input
            type="number"
            step="0.01"
            class="form-control bg-white rounded-3 {{ $errors->has('pret') ? 'is-invalid' : '' }}"
            name="pret"
            id="pret"
            value="{{ old('pret', $produs->pret ?? '') }}"
            required>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="form-check mt-4 ps-4">
            <input class="form-check-input" type="checkbox" name="activ" id="activ" value="1"
                {{ old('activ', $produs->activ ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="activ">Activ</label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12 mb-2 d-flex justify-content-center">
        <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
            <i class="fa-solid fa-save me-1"></i> {{ $buttonText }}
        </button>
        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('produse.index')) }}">
            Renunta
        </a>
    </div>
</div>
