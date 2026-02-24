@php
    $logoSrc = \App\Support\PdfAsset::fromPublic('images/logo.png');
@endphp

<div class="pdf-header">
    @if ($logoSrc)
        <img src="{{ $logoSrc }}" alt="{{ config('app.name', 'Laravel') }}">
    @else
        <div class="pdf-logo-fallback">{{ config('app.name', 'Laravel') }}</div>
    @endif
</div>
<hr class="pdf-divider">
