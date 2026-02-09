@extends ('layouts.app')

@section('content')
@php
    $canWriteEmailTemplates = auth()->user()?->hasPermission('email-templates.write') ?? false;
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-8">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-envelope me-1"></i> Adauga template Email
            </span>
        </div>
        <div class="col-lg-4 text-end">
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('email-templates.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        @if ($canWriteEmailTemplates)
            <form method="POST" action="{{ route('email-templates.store') }}">
                @csrf
                @include('email-templates.form', ['buttonText' => 'Adauga'])
            </form>
        @endif

        <div class="mt-4">
            @include('email-templates.placeholders', ['placeholders' => $placeholders])
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>
@endsection
