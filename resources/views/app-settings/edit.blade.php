@extends ('layouts.app')

@section('content')
@php
    $canWriteAppSettings = auth()->user()?->hasPermission('app-settings.write') ?? false;
@endphp
<div class="container py-4">
    <div class="card mx-auto" style="max-width: 900px; border-radius: 24px;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="badge culoare1 fs-6">
                <i class="fa-solid fa-pen me-1"></i> Editeaza setare
            </span>
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('app-settings.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
        <div class="card-body">
            @include ('errors.errors')

            <form method="POST" action="{{ route('app-settings.update', $appSetting) }}" data-unsaved-guard>
                @csrf
                @method('PUT')
                <fieldset {{ $canWriteAppSettings ? '' : 'disabled' }}>
                    @include('app-settings.form', ['buttonText' => 'Salveaza'])
                </fieldset>
            </form>
        </div>
    </div>
</div>
@endsection
