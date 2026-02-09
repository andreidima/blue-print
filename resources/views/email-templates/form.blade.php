@php
    $emailTemplate = $emailTemplate ?? null;
@endphp
<div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
    <div class="col-lg-6 mb-4">
        <label for="name" class="mb-0 ps-3">Nume<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('name') ? 'is-invalid' : '' }}"
            name="name"
            id="name"
            value="{{ old('name', $emailTemplate->name ?? '') }}"
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
            value="{{ old('color', $emailTemplate->color ?? '#0d6efd') }}"
            required
        >
    </div>
    <div class="col-lg-3 mb-4">
        <label for="key" class="mb-0 ps-3">Cheie</label>
        @if (!empty($emailTemplate?->key))
            <input
                type="text"
                class="form-control bg-light rounded-3"
                id="key"
                value="{{ $emailTemplate->key }}"
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
        <label for="subject" class="mb-0 ps-3">Subiect<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('subject') ? 'is-invalid' : '' }}"
            name="subject"
            id="subject"
            value="{{ old('subject', $emailTemplate->subject ?? '') }}"
            required
        >
    </div>
    <div class="col-12 mb-4">
        <label class="mb-0 ps-3">Mesaj<span class="text-danger">*</span></label>
        @php
            $bodyValue = old('body_html', $emailTemplate->body_html ?? '');
        @endphp
        <input id="body_html" type="hidden" name="body_html" value="{{ $bodyValue }}">
        <trix-editor input="body_html" class="bg-white rounded-3"></trix-editor>
        <div class="small text-muted mt-1">Poti folosi placeholder-uri pentru datele comenzii si clientului.</div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="form-check mt-4 ps-4">
            <input class="form-check-input" type="checkbox" name="active" id="active" value="1"
                {{ old('active', $emailTemplate->active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="active">Activ</label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12 mb-2 d-flex justify-content-center">
        @if ($canWriteEmailTemplates ?? false)
            <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
                <i class="fa-solid fa-save me-1"></i> {{ $buttonText }}
            </button>
        @endif
        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('email-templates.index')) }}">
            Renunta
        </a>
    </div>
</div>
