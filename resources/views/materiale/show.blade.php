@extends ('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-6">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-layer-group me-1"></i> Material: {{ $material->denumire }}
            </span>
        </div>
        <div class="col-lg-6 text-end">
            <a class="btn btn-sm btn-primary text-white border border-dark rounded-3 me-2" href="{{ route('materiale.edit', $material) }}">
                <i class="fa-solid fa-edit me-1"></i> Modifica
            </a>
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('materiale.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>
    <div class="card-body px-3 py-4">
        @include ('errors.errors')
        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="p-3 rounded-3 bg-light">
                    <div class="mb-2"><strong>Denumire:</strong> {{ $material->denumire }}</div>
                    <div class="mb-2"><strong>UM:</strong> {{ $material->unitate_masura }}</div>
                    <div class="mb-2"><strong>Descriere:</strong> {{ $material->descriere ?: '-' }}</div>
                    <div><strong>Activ:</strong> {{ $material->activ ? 'Da' : 'Nu' }}</div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="p-3 rounded-3 bg-light">
                    <div class="mb-2"><strong>Creat la:</strong> {{ optional($material->created_at)->format('d.m.Y H:i') }}</div>
                    <div><strong>Actualizat la:</strong> {{ optional($material->updated_at)->format('d.m.Y H:i') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
