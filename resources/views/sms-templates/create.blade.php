@extends ('layouts.app')

@section('content')
@php
    $canWriteSmsTemplates = auth()->user()?->hasPermission('sms-templates.write') ?? false;
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-6">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-comment-sms me-1"></i> Adauga template SMS
            </span>
        </div>
        <div class="col-lg-6 text-end">
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('sms-templates.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        <div class="row g-4">
            <div class="col-lg-6">
                <form method="POST" action="{{ route('sms-templates.store') }}">
                    @csrf

                    <fieldset {{ $canWriteSmsTemplates ? '' : 'disabled' }}>
                        @include('sms-templates.form', ['buttonText' => 'Adauga'])
                    </fieldset>
                </form>
            </div>
            <div class="col-lg-6">
                @include('sms-templates.placeholders', ['placeholders' => $placeholders])
            </div>
        </div>
    </div>
</div>
@endsection
