@if (!empty($downloadLinks))
    <div style="margin-top:20px;">
        @foreach ($downloadLinks as $link)
            <div style="margin-bottom:10px;">
                <a href="{{ $link['url'] }}"
                    style="display:inline-block; padding:10px 16px; background-color:#1f2937; color:#ffffff; text-decoration:none; border-radius:6px;">
                    Descarca documentul
                </a>
                @if (!empty($link['label']))
                    <div style="margin-top:6px; font-size:12px; color:#6b7280;">{{ $link['label'] }}</div>
                @endif
            </div>
        @endforeach
    </div>
@elseif (!empty($downloadUrl))
    <div style="margin-top:20px;">
        <a href="{{ $downloadUrl }}"
            style="display:inline-block; padding:10px 16px; background-color:#1f2937; color:#ffffff; text-decoration:none; border-radius:6px;">
            Descarca documentul
        </a>
    </div>
@endif
