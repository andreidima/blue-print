@extends('layouts.app')

@section('content')
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <div class="shadow-lg" style="border-radius: 40px;">
        <div class="border border-secondary p-2 culoare2" style="border-radius: 40px 40px 0 0;">
          <span class="badge text-light fs-5">
            <i class="fa-solid fa-box-{{ isset($produs) ? 'open' : 'plus' }} me-1"></i>
            {{ isset($produs) ? 'Editează Produs' : 'Adaugă Produs' }}
          </span>
        </div>

        @include('errors.errors')

        <div class="card-body py-3 px-4 border border-secondary" style="border-radius: 0 0 40px 40px;">
          <form class="needs-validation" novalidate
                method="POST"
                action="{{ isset($produs) ? route('produse.update', $produs->id) : route('produse.store') }}">
            @csrf
            @if(isset($produs))
              @method('PUT')
            @endif

            @include('produse.form', [
              'produs'        => $produs ?? null,
              'allCategorii'  => $allCategorii,
              'buttonText'    => isset($produs) ? 'Salvează modificările' : 'Adaugă Produs'
            ])
          </form>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection
