@php
    $toolbarId = $toolbarId ?? 'trix-toolbar-basic';
@endphp
<trix-toolbar id="{{ $toolbarId }}">
    <div class="trix-button-row">
        <span class="trix-button-group trix-button-group--text-tools" data-trix-button-group="text-tools">
            <button type="button" class="trix-button trix-button--icon trix-button--icon-bold" data-trix-attribute="bold" title="Bold" tabindex="-1">Bold</button>
            <button type="button" class="trix-button trix-button--icon trix-button--icon-italic" data-trix-attribute="italic" title="Italic" tabindex="-1">Italic</button>
        </span>
        <span class="trix-button-group trix-button-group--list-tools" data-trix-button-group="list-tools">
            <button type="button" class="trix-button trix-button--icon trix-button--icon-bullet-list" data-trix-attribute="bullet" title="Buline" tabindex="-1">Buline</button>
            <button type="button" class="trix-button trix-button--icon trix-button--icon-number-list" data-trix-attribute="number" title="Numerotare" tabindex="-1">Numerotare</button>
        </span>
    </div>
</trix-toolbar>
