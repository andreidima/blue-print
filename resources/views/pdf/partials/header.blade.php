@php
    $logoPath = public_path('images/logo.png');
    $logoSrc = '';
    if (is_file($logoPath)) {
        $logoSrc = 'file:///' . str_replace('\\', '/', $logoPath);
    }
@endphp

<div class="pdf-header">
    @if ($logoSrc)
        <img src="{{ $logoSrc }}" alt="{{ config('app.name', 'Laravel') }}">
    @else
        <div class="pdf-logo-fallback">{{ config('app.name', 'Laravel') }}</div>
    @endif
</div>
<hr class="pdf-divider">
