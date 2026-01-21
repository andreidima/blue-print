@extends ('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-6">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-address-book me-1"></i> Client: {{ $client->nume_complet }}
            </span>
        </div>
        <div class="col-lg-6 text-end">
            <a class="btn btn-sm btn-primary text-white border border-dark rounded-3 me-2" href="{{ route('clienti.edit', $client) }}">
                <i class="fa-solid fa-edit me-1"></i> Modifica
            </a>
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('clienti.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="p-3 rounded-3 bg-light">
                    <div class="mb-2">
                        <strong>Tip:</strong>
                        {{ ($client->type ?? 'pf') === 'pj' ? 'Persoana juridica' : 'Persoana fizica' }}
                    </div>
                    <div class="mb-2"><strong>Nume complet:</strong> {{ $client->nume_complet }}</div>
                    <div class="mb-2"><strong>Telefon:</strong> {{ $client->telefon }}</div>
                    <div class="mb-2"><strong>Email:</strong> {{ $client->email }}</div>
                    <div><strong>Adresa:</strong> {{ $client->adresa }}</div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="p-3 rounded-3 bg-light">
                    <div class="mb-2"><strong>Creat la:</strong> {{ optional($client->created_at)->format('d.m.Y H:i') }}</div>
                    <div><strong>Actualizat la:</strong> {{ optional($client->updated_at)->format('d.m.Y H:i') }}</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12 mb-3">
                <div class="p-3 rounded-3 bg-light">
                    @if (($client->type ?? 'pf') === 'pj')
                        <div class="mb-2"><strong>Nr. Reg. com.:</strong> {{ $client->reg_com }}</div>
                        <div class="mb-2"><strong>CUI:</strong> {{ $client->cui }}</div>
                        <div class="mb-2"><strong>IBAN:</strong> {{ $client->iban }}</div>
                        <div class="mb-2"><strong>Banca:</strong> {{ $client->banca }}</div>
                        <div class="mb-2"><strong>Reprezentant:</strong> {{ $client->reprezentant }}</div>
                        <div><strong>Reprezentant functie:</strong> {{ $client->reprezentant_functie }}</div>
                    @else
                        <div class="mb-2"><strong>CNP:</strong> {{ $client->cnp }}</div>
                        <div><strong>Sex:</strong> {{ $client->sex }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
