@php
    $setting = $appSetting ?? null;
    $selectedType = old('type', $setting?->type ?? \App\Models\AppSetting::TYPE_TEXT);
    $isLongText = $selectedType === \App\Models\AppSetting::TYPE_LONG_TEXT;
@endphp

<div class="mb-3">
    <label for="label" class="form-label">Nume</label>
    <input type="text" id="label" name="label" class="form-control rounded-3" value="{{ old('label', $setting?->label ?? '') }}" required maxlength="150">
</div>

<div class="mb-3">
    <label for="key" class="form-label">Cheie</label>
    <input type="text" id="key" name="key" class="form-control rounded-3" value="{{ old('key', $setting?->key ?? '') }}" required maxlength="150" pattern="[a-z0-9._-]+">
    <div class="small text-muted mt-1">Exemplu: <code>google_review_url</code>.</div>
</div>

<div class="mb-3">
    <label for="type" class="form-label">Tip</label>
    <select id="type" name="type" class="form-select rounded-3">
        @foreach ($typeOptions as $typeValue => $typeLabel)
            <option value="{{ $typeValue }}" {{ $selectedType === $typeValue ? 'selected' : '' }}>{{ $typeLabel }}</option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="value" class="form-label">Valoare</label>
    @if ($isLongText)
        <textarea id="value" name="value" class="form-control rounded-3" rows="6" maxlength="10000">{{ old('value', $setting?->value ?? '') }}</textarea>
    @else
        <input type="text" id="value" name="value" class="form-control rounded-3" value="{{ old('value', $setting?->value ?? '') }}" maxlength="10000">
    @endif
</div>

<div class="mb-4">
    <label for="description" class="form-label">Descriere (optional)</label>
    <input type="text" id="description" name="description" class="form-control rounded-3" value="{{ old('description', $setting?->description ?? '') }}" maxlength="255">
</div>

<div class="d-flex justify-content-between">
    <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('app-settings.index')) }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
    </a>
    <button type="submit" class="btn btn-primary text-white rounded-3">
        <i class="fa-solid fa-floppy-disk me-1"></i> {{ $buttonText }}
    </button>
</div>
