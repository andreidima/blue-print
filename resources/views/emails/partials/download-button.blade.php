@if (!empty($downloadLinks))
    <div style="margin-top:20px;">
        @foreach ($downloadLinks as $link)
            @php
                $buttonText = trim((string) ($link['label'] ?? ''));
                if ($buttonText === '') {
                    $buttonText = 'Descarca documentul';
                }
            @endphp
            <div style="margin-bottom:8px;">
                <a href="{{ $link['url'] }}"
                    style="display:inline-block; padding:6px 10px; background-color:#1f2937; color:#ffffff; text-decoration:none; border-radius:5px; font-size:12px; line-height:1.2;">
                    {{ $buttonText }}
                </a>
            </div>
        @endforeach
    </div>
@elseif (!empty($downloadUrl))
    <div style="margin-top:20px;">
        <a href="{{ $downloadUrl }}"
            style="display:inline-block; padding:6px 10px; background-color:#1f2937; color:#ffffff; text-decoration:none; border-radius:5px; font-size:12px; line-height:1.2;">
            Descarca documentul
        </a>
    </div>
@endif
