@extends ('layouts.app')

@section('content')
@php
    $canWriteProduse = auth()->user()?->hasPermission('produse.write') ?? false;
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-6">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-layer-group me-1"></i>
                {{ isset($material) ? 'Modifica material' : 'Adauga material' }}
            </span>
        </div>
        <div class="col-lg-6 text-end">
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('materiale.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>
    <div class="card-body px-3 py-4">
        @include ('errors.errors')
        <form method="POST" action="{{ isset($material) ? route('materiale.update', $material) : route('materiale.store') }}" data-unsaved-guard>
            @csrf
            @if(isset($material))
                @method('PUT')
            @endif
            <fieldset {{ $canWriteProduse ? '' : 'disabled' }}>
                @include('materiale.form', ['buttonText' => isset($material) ? 'Salveaza' : 'Adauga'])
            </fieldset>
        </form>
    </div>
</div>
@endsection
