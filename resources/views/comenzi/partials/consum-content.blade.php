@php
    $canWriteConsum = $canWriteConsum ?? false;
    $totalConsumRows = $comanda->produse->sum(fn ($item) => $item->consumuri->count());
    $tableColumnCount = $canWriteConsum ? 10 : 9;
    $rebutCellStyle = 'background-color: #fff1f1;';
    $rebutInputStyle = 'background-color: #fff7f7; border-color: #e8b9b9;';
    $formatQuantity = static function ($value) {
        $formatted = number_format((float) $value, 4, '.', '');
        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    };
    $summaryRows = $comanda->produse
        ->flatMap(fn ($linie) => $linie->consumuri)
        ->groupBy(function ($consum) {
            return mb_strtolower($consum->materialLabel()) . '||' . mb_strtolower((string) $consum->unitate_masura);
        })
        ->map(function ($group) {
            $first = $group->first();
            $equipmentLabels = $group
                ->map(fn ($item) => $item->echipamentLabel())
                ->filter(fn ($label) => $label !== '-')
                ->unique()
                ->values()
                ->all();

            return [
                'material' => $first?->materialLabel() ?? '-',
                'unitate_masura' => $first?->unitate_masura ?? '',
                'consum' => (float) $group->sum(fn ($item) => (float) $item->cantitate_totala),
                'rebut' => (float) $group->sum(fn ($item) => (float) $item->cantitate_rebutata),
                'total' => (float) $group->sum(fn ($item) => (float) $item->totalConsumCuRebut()),
                'echipamente' => $equipmentLabels === [] ? '-' : implode(', ', $equipmentLabels),
            ];
        })
        ->sortBy(fn ($row) => mb_strtolower($row['material']))
        ->values();
@endphp

<div class="p-3 rounded-3 bg-light border">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="fw-semibold">Consum materiale</div>
            <div class="small text-muted">Rebutul se introduce direct pe acelasi rand de consum.</div>
        </div>
        <div class="small text-muted">
            Randuri consum: {{ $totalConsumRows }}
        </div>
    </div>

    @forelse ($comanda->produse as $linie)
        @php
            $productLabel = $linie->custom_denumire ?? ($linie->produs->denumire ?? '-');
        @endphp
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom-0 pb-0">
                <div class="fw-semibold">{{ $productLabel }}</div>
                <div class="small text-muted">Cantitate comanda: {{ $formatQuantity($linie->cantitate) }}</div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="4%" class="text-center">#</th>
                                <th width="20%">Material</th>
                                <th width="8%">UM</th>
                                <th width="12%">Consum</th>
                                <th width="10%" style="{{ $rebutCellStyle }}">Rebut</th>
                                <th width="12%">Total</th>
                                <th width="14%">Echipament</th>
                                <th width="12%">Observatii</th>
                                <th width="6%">Info</th>
                                @if ($canWriteConsum)
                                    <th width="4%" class="text-end">Actiuni</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($linie->consumuri as $consum)
                                @php
                                    $updateFormId = 'consum-update-' . $consum->id;
                                    $materialLabel = $consum->materialLabel();
                                    $equipmentLabel = $consum->echipamentLabel();
                                @endphp
                                <tr>
                                    <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                    <td>
                                        @if ($canWriteConsum)
                                            <form id="{{ $updateFormId }}" method="POST" action="{{ route('comenzi.produse.consumuri.update', [$comanda, $linie, $consum]) }}" data-ajax-form data-ajax-scope="consum" data-consum-form>
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="material_id" value="{{ $consum->material_id }}" data-consum-material-id>
                                                <input type="hidden" name="material_add_to_nomenclator" value="0" data-consum-material-add-flag>
                                                <input type="hidden" name="echipament_id" value="{{ $consum->echipament_id }}" data-consum-equipment-id>
                                                <input type="hidden" name="echipament_add_to_nomenclator" value="0" data-consum-equipment-add-flag>
                                            </form>
                                            <div data-consum-material-selector data-search-url="{{ route('materiale.select-options') }}">
                                                <input type="text" class="form-control form-control-sm" name="material_denumire" value="{{ $materialLabel }}" form="{{ $updateFormId }}" autocomplete="off" data-consum-material-query>
                                                <div class="list-group w-100 shadow-sm mt-1 d-none" style="max-height: 220px; overflow: auto;" data-consum-material-results></div>
                                            </div>
                                        @else
                                            {{ $materialLabel }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($canWriteConsum)
                                            <input type="text" class="form-control form-control-sm" name="unitate_masura" value="{{ $consum->unitate_masura }}" form="{{ $updateFormId }}" data-consum-unit-input required>
                                        @else
                                            {{ $consum->unitate_masura }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($canWriteConsum)
                                            <input type="number" min="0.0001" step="0.0001" class="form-control form-control-sm" name="cantitate_totala" value="{{ $formatQuantity($consum->cantitate_totala) }}" form="{{ $updateFormId }}" required>
                                        @else
                                            {{ $formatQuantity($consum->cantitate_totala) }}
                                        @endif
                                    </td>
                                    <td style="{{ $rebutCellStyle }}">
                                        @if ($canWriteConsum)
                                            <input type="number" min="0" step="0.0001" class="form-control form-control-sm" style="{{ $rebutInputStyle }}" name="cantitate_rebutata" value="{{ $formatQuantity($consum->cantitate_rebutata) }}" form="{{ $updateFormId }}">
                                        @else
                                            {{ $formatQuantity($consum->cantitate_rebutata) }}
                                        @endif
                                    </td>
                                    <td>{{ $formatQuantity($consum->totalConsumCuRebut()) }}</td>
                                    <td>
                                        @if ($canWriteConsum)
                                            <div data-consum-equipment-selector data-search-url="{{ route('echipamente.select-options') }}">
                                                <input type="text" class="form-control form-control-sm" name="echipament_denumire" value="{{ $equipmentLabel !== '-' ? $equipmentLabel : '' }}" form="{{ $updateFormId }}" autocomplete="off" data-consum-equipment-query placeholder="Optional">
                                                <div class="list-group w-100 shadow-sm mt-1 d-none" style="max-height: 220px; overflow: auto;" data-consum-equipment-results></div>
                                            </div>
                                        @else
                                            {{ $equipmentLabel }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($canWriteConsum)
                                            <div class="d-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" title="Adauga sau editeaza observatii" aria-label="Adauga sau editeaza observatii" data-consum-observation-toggle>
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                                @if (filled($consum->observatii))
                                                    <button type="button" class="btn btn-sm btn-outline-success" title="Vezi observatiile" aria-label="Vezi observatiile" data-consum-observation-toggle>
                                                        <i class="fa-regular fa-note-sticky"></i>
                                                    </button>
                                                @endif
                                            </div>
                                            <div class="d-none mt-2" data-consum-observation-wrap>
                                                <textarea class="form-control form-control-sm" rows="2" name="observatii" form="{{ $updateFormId }}" placeholder="Observatii optionale">{{ $consum->observatii }}</textarea>
                                            </div>
                                        @else
                                            @if (filled($consum->observatii))
                                                <button type="button" class="btn btn-sm btn-outline-success" title="Vezi observatiile" aria-label="Vezi observatiile" data-consum-observation-toggle>
                                                    <i class="fa-regular fa-note-sticky"></i>
                                                </button>
                                                <div class="d-none mt-2 small" data-consum-observation-wrap>
                                                    {{ $consum->observatii }}
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-audit-toggle>Audit</button>
                                    </td>
                                    @if ($canWriteConsum)
                                        <td class="text-end">
                                            <div class="d-inline-flex align-items-center flex-nowrap gap-1">
                                                <button type="submit" form="{{ $updateFormId }}" class="btn btn-sm btn-primary" title="Salveaza randul" aria-label="Salveaza randul">
                                                    <i class="fa-solid fa-save"></i>
                                                </button>
                                                <form method="POST" action="{{ route('comenzi.produse.consumuri.destroy', [$comanda, $linie, $consum]) }}" data-ajax-form data-ajax-scope="consum" data-confirm="Sigur vrei sa elimini acest rand de consum?" class="d-inline m-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Elimina randul" aria-label="Elimina randul">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                                <tr class="d-none" data-audit-panel>
                                    <td colspan="{{ $tableColumnCount }}" class="bg-white">
                                        <div class="small text-muted">
                                            <strong>Adaugat:</strong> {{ optional($consum->created_at)->format('d.m.Y H:i') ?? '-' }}
                                            de {{ optional($consum->createdBy)->name ?? '-' }}.
                                            @if ($consum->updated_at && $consum->updated_at->ne($consum->created_at))
                                                <strong class="ms-2">Actualizat:</strong> {{ $consum->updated_at->format('d.m.Y H:i') }}
                                                de {{ optional($consum->updatedBy)->name ?? '-' }}.
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $tableColumnCount }}" class="text-center text-muted">
                                        Nu exista consumuri inregistrate pentru acest produs.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($canWriteConsum)
                    <form method="POST" action="{{ route('comenzi.produse.consumuri.store', [$comanda, $linie]) }}" data-ajax-form data-ajax-scope="consum" data-ajax-reset data-consum-form class="mt-3">
                        @csrf
                        <input type="hidden" name="material_id" value="" data-consum-material-id>
                        <input type="hidden" name="material_add_to_nomenclator" value="0" data-consum-material-add-flag>
                        <input type="hidden" name="echipament_id" value="" data-consum-equipment-id>
                        <input type="hidden" name="echipament_add_to_nomenclator" value="0" data-consum-equipment-add-flag>

                        <div class="row g-3 align-items-start">
                            <div class="col-lg-3">
                                <label class="mb-0 ps-3">Material</label>
                                <div data-consum-material-selector data-search-url="{{ route('materiale.select-options') }}">
                                    <input type="text" class="form-control bg-white rounded-3" name="material_denumire" autocomplete="off" placeholder="Cauta sau scrie un material" data-consum-material-query required>
                                    <div class="list-group w-100 shadow-sm mt-1 d-none" style="max-height: 220px; overflow: auto;" data-consum-material-results></div>
                                </div>
                            </div>
                            <div class="col-lg-2">
                                <label class="mb-0 ps-3">UM</label>
                                <input type="text" class="form-control bg-white rounded-3" name="unitate_masura" placeholder="buc, mp, rola" data-consum-unit-input required>
                            </div>
                            <div class="col-lg-2">
                                <label class="mb-0 ps-3">Consum</label>
                                <input type="number" min="0.0001" step="0.0001" class="form-control bg-white rounded-3" name="cantitate_totala" required>
                            </div>
                            <div class="col-lg-2">
                                <label class="mb-0 ps-3">Rebut</label>
                                <input type="number" min="0" step="0.0001" class="form-control rounded-3" style="{{ $rebutInputStyle }}" name="cantitate_rebutata" value="0">
                            </div>
                            <div class="col-lg-3">
                                <label class="mb-0 ps-3">Echipament</label>
                                <div data-consum-equipment-selector data-search-url="{{ route('echipamente.select-options') }}">
                                    <input type="text" class="form-control bg-white rounded-3" name="echipament_denumire" autocomplete="off" placeholder="Cauta sau scrie un echipament" data-consum-equipment-query>
                                    <div class="list-group w-100 shadow-sm mt-1 d-none" style="max-height: 220px; overflow: auto;" data-consum-equipment-results></div>
                                </div>
                            </div>
                            <div class="col-lg-10 d-none" data-consum-observation-wrap>
                                <label class="mb-0 ps-3">Observatii</label>
                                <textarea class="form-control bg-white rounded-3" name="observatii" rows="2" placeholder="Observatii optionale"></textarea>
                            </div>
                            <div class="col-lg-2 text-end">
                                <label class="mb-0 ps-3">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Adauga observatii" aria-label="Adauga observatii" data-consum-observation-toggle>
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                    <button type="submit" class="btn btn-sm btn-outline-primary flex-grow-1">
                                        <i class="fa-solid fa-plus me-1"></i> Adauga
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="text-muted">Adauga mai intai produse pe comanda pentru a putea inregistra consumuri.</div>
    @endforelse

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-bottom-0 pb-0">
            <div class="fw-semibold">Centralizare materiale</div>
            <div class="small text-muted">Totaluri cumulate pentru toate produsele din comanda.</div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="6%" class="text-center">#</th>
                            <th width="26%">Material</th>
                            <th width="10%">UM</th>
                            <th width="18%">Echipament utilizat</th>
                            <th width="14%">Consum</th>
                            <th width="12%" style="{{ $rebutCellStyle }}">Rebut</th>
                            <th width="14%">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summaryRows as $summaryRow)
                            <tr>
                                <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                <td>{{ $summaryRow['material'] }}</td>
                                <td>{{ $summaryRow['unitate_masura'] }}</td>
                                <td>{{ $summaryRow['echipamente'] }}</td>
                                <td>{{ $formatQuantity($summaryRow['consum']) }}</td>
                                <td style="{{ $rebutCellStyle }}">{{ $formatQuantity($summaryRow['rebut']) }}</td>
                                <td>{{ $formatQuantity($summaryRow['total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    Nu exista materiale de centralizat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
