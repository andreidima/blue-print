@php
    $mockupTypes = $mockupTypes ?? \App\Models\Mockup::typeOptions();
    $canBypassDailyEditLock = $canBypassDailyEditLock ?? false;
    $canOperateFacturaFiles = $canOperateFacturaFiles ?? false;
    $sentFacturaIds = \App\Support\ComandaEmailAttachmentSupport::collectSentSourceIds(
        $comanda,
        \App\Support\ComandaEmailAttachmentSupport::KIND_FACTURA
    );
    $sentAtasamentIds = \App\Support\ComandaEmailAttachmentSupport::collectSentSourceIds(
        $comanda,
        \App\Support\ComandaEmailAttachmentSupport::KIND_ATASAMENT
    );
    $sentMockupIds = \App\Support\ComandaEmailAttachmentSupport::collectSentSourceIds(
        $comanda,
        \App\Support\ComandaEmailAttachmentSupport::KIND_MOCKUP
    );
    $lockTimezone = (string) config('app.timezone', 'UTC');
    $lockNow = now($lockTimezone);
    $mockupGroups = $comanda->mockupuri->groupBy(fn ($item) => $item->tip ?: \App\Models\Mockup::TIP_INFO_MOCKUP);
    $mockupCountsByType = collect($mockupTypes)
        ->map(fn ($label, $type) => ($mockupGroups->get($type) ?? collect())->count())
        ->all();
    $resolveRoleMeta = function (?App\Models\User $user): array {
        $role = $user?->primaryActiveRole();
        return [
            'name' => $role?->name ?? 'Utilizator',
            'color' => $role?->color ?? '#6c757d',
        ];
    };
@endphp

<div class="row mb-4">
    <div class="col-lg-6 mb-3">
        <h6 class="mb-3 js-comanda-section" id="atasamente" data-collapse="#collapse-fisiere">Alte documente</h6>
        <form method="POST" action="{{ route('comenzi.atasamente.store', $comanda) }}" enctype="multipart/form-data" class="mb-3" data-ajax-form data-ajax-scope="fisiere" data-ajax-reset>
            @csrf
            <fieldset {{ $canWriteAtasamente ? '' : 'disabled' }}>
                <div class="input-group">
                    <input type="file" class="form-control" name="atasament[]" multiple required>
                    @if ($canWriteAtasamente)
                        <button type="submit" class="btn btn-outline-primary">Incarca</button>
                    @endif
                </div>
                <div class="small text-muted mt-1">Maxim 10MB per fisier. Foloseste aceasta zona pentru contracte, anexe si alte documente trimise clientului.</div>
            </fieldset>
        </form>
        <ul class="list-group">
            @forelse ($comanda->atasamente as $atasament)
                @php
                    $atasamentLockedAt = $atasament->created_at
                        ? $atasament->created_at->copy()->setTimezone($lockTimezone)->startOfDay()->addDay()
                        : null;
                    $atasamentIsLocked = $atasamentLockedAt ? $lockNow->gte($atasamentLockedAt) : false;
                    $atasamentWasSentByEmail = in_array($atasament->id, $sentAtasamentIds, true);
                    $canDeleteAtasament = $canWriteAtasamente
                        && !$atasamentWasSentByEmail
                        && (!$atasamentIsLocked || $canBypassDailyEditLock);
                    $atasamentRoleMeta = $resolveRoleMeta($atasament->uploadedBy);
                @endphp
                <li class="list-group-item d-flex justify-content-between align-items-center" style="border-left: 4px solid {{ $atasamentRoleMeta['color'] }};">
                    <div class="me-2">
                        <div class="small text-muted">
                            {{ optional($atasament->created_at)->format('d.m.Y H:i') ?? '-' }}
                            @if ($atasament->uploadedBy)
                                - {{ $atasament->uploadedBy->name }}
                            @endif
                            <span class="badge ms-1" style="background-color: {{ $atasamentRoleMeta['color'] }};">{{ $atasamentRoleMeta['name'] }}</span>
                        </div>
                        <a href="{{ $atasament->fileUrl() }}" target="_blank" rel="noopener">{{ $atasament->original_name }}</a>
                        <div class="small text-muted">{{ number_format($atasament->size / 1024, 1) }} KB</div>
                        @if ($atasamentLockedAt)
                            @if ($atasamentIsLocked)
                                <div class="small">Blocat din {{ $atasamentLockedAt->format('d.m.Y H:i') }}.</div>
                            @else
                                <div class="small">Se blocheaza la {{ $atasamentLockedAt->format('d.m.Y H:i') }}.</div>
                            @endif
                        @endif
                        @if ($atasamentWasSentByEmail)
                            <div class="small text-muted">Trimis deja prin email. Stergerea este blocata.</div>
                        @endif
                    </div>
                    <div class="d-flex gap-1">
                        <a class="btn btn-sm btn-primary" href="{{ $atasament->fileUrl() }}" target="_blank" rel="noopener" title="Vezi" aria-label="Vezi">
                            <i class="fa-regular fa-eye"></i>
                        </a>
                        <a class="btn btn-sm btn-success" href="{{ $atasament->downloadUrl() }}" title="Download" aria-label="Download">
                            <i class="fa-solid fa-download"></i>
                        </a>
                        @if ($canDeleteAtasament)
                            <form method="POST" action="{{ $atasament->destroyUrl() }}" data-confirm="Stergi atasamentul?" data-ajax-form data-ajax-scope="fisiere">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Sterge" aria-label="Sterge">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="list-group-item text-muted">Nu exista alte documente.</li>
            @endforelse
        </ul>
    </div>

    <div class="col-lg-6 mb-3">
        <h6 class="mb-3 js-comanda-section" id="facturi" data-collapse="#collapse-fisiere">Facturi</h6>
        @if ($canViewFacturi)
            <form method="POST" action="{{ route('comenzi.facturi.store', $comanda) }}" enctype="multipart/form-data" class="mb-3" data-ajax-form data-ajax-scope="fisiere" data-ajax-reset>
                @csrf
                <fieldset {{ $canManageFacturi ? '' : 'disabled' }}>
                    <div class="input-group">
                        <input type="file" class="form-control" name="factura[]" multiple required>
                        @if ($canManageFacturi)
                            <button type="submit" class="btn btn-outline-primary">Incarca</button>
                        @endif
                    </div>
                    <div class="small text-muted mt-1">Maxim 10MB per fisier. Poti selecta mai multe facturi odata.</div>
                </fieldset>
            </form>
            <ul class="list-group mb-3">
                @forelse ($comanda->facturi as $factura)
                    @php
                        $facturaLockedAt = $factura->created_at
                            ? $factura->created_at->copy()->setTimezone($lockTimezone)->startOfDay()->addDay()
                            : null;
                        $facturaIsLocked = $facturaLockedAt ? $lockNow->gte($facturaLockedAt) : false;
                        $facturaWasSentByEmail = in_array($factura->id, $sentFacturaIds, true);
                        $canDeleteFactura = $canManageFacturi
                            && $canOperateFacturaFiles
                            && !$facturaWasSentByEmail
                            && (!$facturaIsLocked || $canBypassDailyEditLock);
                        $facturaRoleMeta = $resolveRoleMeta($factura->uploadedBy);
                    @endphp
                    <li class="list-group-item d-flex justify-content-between align-items-center" style="border-left: 4px solid {{ $facturaRoleMeta['color'] }};">
                        <div class="me-2">
                            <div class="small text-muted">
                                {{ optional($factura->created_at)->format('d.m.Y H:i') ?? '-' }}
                                @if ($factura->uploadedBy)
                                    - {{ $factura->uploadedBy->name }}
                                @endif
                                <span class="badge ms-1" style="background-color: {{ $facturaRoleMeta['color'] }};">{{ $facturaRoleMeta['name'] }}</span>
                            </div>
                            @if ($canOperateFacturaFiles)
                                <a href="{{ $factura->fileUrl() }}" target="_blank" rel="noopener">{{ $factura->original_name }}</a>
                            @else
                                <span>{{ $factura->original_name }}</span>
                            @endif
                            <div class="small text-muted">{{ number_format($factura->size / 1024, 1) }} KB</div>
                            @if ($facturaLockedAt)
                                @if ($facturaIsLocked)
                                    <div class="small">Blocat din {{ $facturaLockedAt->format('d.m.Y H:i') }}.</div>
                                @else
                                    <div class="small">Se blocheaza la {{ $facturaLockedAt->format('d.m.Y H:i') }}.</div>
                                @endif
                            @endif
                            @if ($facturaWasSentByEmail)
                                <div class="small text-muted">Trimisa deja prin email. Stergerea este blocata.</div>
                            @endif
                        </div>
                        @if ($canOperateFacturaFiles)
                            <div class="d-flex gap-1">
                                <a class="btn btn-sm btn-primary" href="{{ $factura->fileUrl() }}" target="_blank" rel="noopener" title="Vezi" aria-label="Vezi">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <a class="btn btn-sm btn-success" href="{{ $factura->downloadUrl() }}" title="Download" aria-label="Download">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                                @if ($canDeleteFactura)
                                    <form method="POST" action="{{ $factura->destroyUrl() }}" data-confirm="Stergi factura?" data-ajax-form data-ajax-scope="fisiere">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Sterge" aria-label="Sterge">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-muted">Nu exista facturi.</li>
                @endforelse
            </ul>
            @if (!$clientEmail)
                <div class="text-muted small">Clientul nu are emailuri setate.</div>
            @endif
        @else
            <div class="text-muted">Facturile pot fi gestionate doar de supervizori.</div>
        @endif
    </div>
</div>

<div class="row mb-4">
    @foreach ($mockupTypes as $type => $label)
        @php
            $mockups = $mockupGroups->get($type, collect());
            $isFirstInfo = $loop->first;
        @endphp
        <div class="col-lg-6 col-xl-3 mb-3">
            <h6
                class="mb-3 js-comanda-section d-flex justify-content-between align-items-center"
                {!! $isFirstInfo ? 'id="mockupuri"' : '' !!}
                data-collapse="#collapse-fisiere"
            >
                <span>{{ $label }}</span>
                <span class="badge bg-secondary">{{ $mockupCountsByType[$type] ?? 0 }}</span>
            </h6>
            <form method="POST" action="{{ route('comenzi.mockupuri.store', $comanda) }}" enctype="multipart/form-data" class="mb-3" data-ajax-form data-ajax-scope="fisiere" data-ajax-reset>
                @csrf
                <fieldset {{ $canWriteMockupuri ? '' : 'disabled' }}>
                    <input type="hidden" name="tip" value="{{ $type }}">
                    <div class="mb-2">
                        <input type="file" class="form-control" name="mockup[]" multiple required>
                    </div>
                    <div class="small text-muted mt-1 mb-2">Maxim 10MB per fisier. Poti selecta mai multe fisiere odata.</div>
                    <div class="mb-2">
                        <input type="text" class="form-control" name="comentariu" placeholder="Comentariu (optional)">
                    </div>
                    @if ($canWriteMockupuri)
                        <button type="submit" class="btn btn-outline-primary">Incarca</button>
                    @endif
                </fieldset>
            </form>
            <ul class="list-group">
                @forelse ($mockups as $mockup)
                    @php
                        $mockupLockedAt = $mockup->created_at
                            ? $mockup->created_at->copy()->setTimezone($lockTimezone)->startOfDay()->addDay()
                            : null;
                        $mockupIsLocked = $mockupLockedAt ? $lockNow->gte($mockupLockedAt) : false;
                        $mockupWasSentByEmail = in_array($mockup->id, $sentMockupIds, true);
                        $canDeleteMockup = $canWriteMockupuri
                            && !$mockupWasSentByEmail
                            && (!$mockupIsLocked || $canBypassDailyEditLock);
                        $mockupRoleMeta = $resolveRoleMeta($mockup->uploadedBy);
                    @endphp
                    <li class="list-group-item" style="border-left: 4px solid {{ $mockupRoleMeta['color'] }};">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="me-2">
                                <div class="small text-muted">
                                    {{ optional($mockup->created_at)->format('d.m.Y H:i') ?? '-' }}
                                    @if ($mockup->uploadedBy)
                                        - {{ $mockup->uploadedBy->name }}
                                    @endif
                                    <span class="badge ms-1" style="background-color: {{ $mockupRoleMeta['color'] }};">{{ $mockupRoleMeta['name'] }}</span>
                                </div>
                                <a href="{{ $mockup->fileUrl() }}" target="_blank" rel="noopener">{{ $mockup->original_name }}</a>
                                <div class="small text-muted">{{ number_format($mockup->size / 1024, 1) }} KB</div>
                                @if ($mockupLockedAt)
                                    @if ($mockupIsLocked)
                                        <div class="small">Blocat din {{ $mockupLockedAt->format('d.m.Y H:i') }}.</div>
                                    @else
                                        <div class="small">Se blocheaza la {{ $mockupLockedAt->format('d.m.Y H:i') }}.</div>
                                    @endif
                                @endif
                                @if ($mockupWasSentByEmail)
                                    <div class="small text-muted">Trimis deja prin email. Stergerea este blocata.</div>
                                @endif
                            </div>
                            <div class="d-flex gap-1">
                                <a class="btn btn-sm btn-primary" href="{{ $mockup->fileUrl() }}" target="_blank" rel="noopener" title="Vezi" aria-label="Vezi">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <a class="btn btn-sm btn-success" href="{{ $mockup->downloadUrl() }}" title="Download" aria-label="Download">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                                @if ($canDeleteMockup)
                                    <form method="POST" action="{{ $mockup->destroyUrl() }}" data-confirm="Stergi fisierul?" data-ajax-form data-ajax-scope="fisiere">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Sterge" aria-label="Sterge">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @if ($mockup->comentariu)
                            <div class="small text-muted mt-1">{{ $mockup->comentariu }}</div>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-muted">Nu exista fisiere.</li>
                @endforelse
            </ul>
        </div>
    @endforeach
</div>
