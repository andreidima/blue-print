@php
    $smsTemplate = $smsTemplate ?? null;
@endphp
<div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
    <div class="col-lg-5 mb-4">
        <label for="name" class="mb-0 ps-3">Nume<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('name') ? 'is-invalid' : '' }}"
            name="name"
            id="name"
            value="{{ old('name', $smsTemplate->name ?? '') }}"
            required
        >
    </div>
    <div class="col-lg-3 mb-4">
        <label for="color" class="mb-0 ps-3">Culoare<span class="text-danger">*</span></label>
        <input
            type="color"
            class="form-control form-control-color bg-white rounded-3 {{ $errors->has('color') ? 'is-invalid' : '' }}"
            name="color"
            id="color"
            value="{{ old('color', $smsTemplate->color ?? '#0d6efd') }}"
            required
        >
    </div>
    <div class="col-lg-4 mb-4">
        <label for="key" class="mb-0 ps-3">Cheie</label>
        @if (!empty($smsTemplate?->key))
            <input
                type="text"
                class="form-control bg-light rounded-3"
                id="key"
                value="{{ $smsTemplate->key }}"
                readonly
            >
        @else
            <input
                type="text"
                class="form-control bg-light rounded-3"
                id="key"
                value="Se genereaza automat"
                readonly
            >
        @endif
    </div>
    <div class="col-12 mb-4">
        <label for="body" class="mb-0 ps-3">Mesaj<span class="text-danger">*</span></label>
        <textarea
            class="form-control bg-white rounded-3 {{ $errors->has('body') ? 'is-invalid' : '' }}"
            name="body"
            id="body"
            rows="6"
            required
        >{{ old('body', $smsTemplate->body ?? '') }}</textarea>
        <div class="small text-muted mt-1">Poti folosi placeholder-uri: {client}, {comanda_id}, {total}, {livrare} etc.</div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="form-check mt-4 ps-4">
            <input class="form-check-input" type="checkbox" name="active" id="active" value="1"
                {{ old('active', $smsTemplate->active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="active">Activ</label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12 mb-2 d-flex justify-content-center">
        @if ($canWriteSmsTemplates ?? false)
            <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
                <i class="fa-solid fa-save me-1"></i> {{ $buttonText }}
            </button>
        @endif
        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('sms-templates.index')) }}">
            Renunta
        </a>
    </div>
</div>
