@extends ('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-6">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-address-book me-1"></i>
                {{ isset($client) ? 'Modifica client' : 'Adauga client' }}
            </span>
        </div>
        <div class="col-lg-6 text-end">
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('clienti.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        <form method="POST" action="{{ isset($client) ? route('clienti.update', $client) : route('clienti.store') }}">
            @csrf
            @if(isset($client))
                @method('PUT')
            @endif

            @include('clienti.form', ['buttonText' => isset($client) ? 'Salveaza' : 'Adauga'])
        </form>
    </div>
</div>
@endsection
