<div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
    <div class="col-lg-8 mb-4">
        <label for="name" class="mb-0 ps-3">Nume<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('name') ? 'is-invalid' : '' }}"
            name="name"
            id="name"
            value="{{ old('name', $role->name ?? '') }}"
            required>
    </div>

    <div class="col-lg-4 mb-4">
        <label for="color" class="mb-0 ps-3">Culoare<span class="text-danger">*</span></label>
        <input
            type="color"
            class="form-control form-control-color bg-white rounded-3 {{ $errors->has('color') ? 'is-invalid' : '' }}"
            name="color"
            id="color"
            value="{{ old('color', $role->color ?? '#6c757d') }}"
            title="Alege culoarea rolului"
            required>
        <div class="form-text ps-3">
            Exemplu: <code>#0d6efd</code>
        </div>
    </div>

    @if (isset($role))
        <div class="col-lg-12 mb-2">
            <div class="ps-3">
                <span class="text-muted">Slug:</span>
                <code>{{ $role->slug }}</code>
            </div>
        </div>
    @endif
</div>

<div class="row">
    <div class="col-lg-12 mb-2 d-flex justify-content-center">
        <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
            <i class="fa-solid fa-save me-1"></i> {{ $buttonText }}
        </button>
        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('roles.index')) }}">
            Renunță
        </a>
    </div>
</div>

