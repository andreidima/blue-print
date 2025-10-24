@extends('layouts.app')

@section('content')
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <div class="shadow-lg" style="border-radius: 40px;">
        <div class="border border-secondary p-2 culoare2" style="border-radius: 40px 40px 0 0;">
          <span class="badge text-light fs-5">
            <i class="fa-solid fa-box me-1"></i> Detalii Produs
          </span>
        </div>

        <div class="card-body border border-secondary p-4" style="border-radius: 0 0 40px 40px;">
          <div class="row mb-3">
            <div class="col-md-6 mb-2"><strong>ID:</strong> {{ $produs->id }}</div>
            <div class="col-md-6 mb-2"><strong>Categorie:</strong> {{ $produs->categorie->nume }}</div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6 mb-2"><strong>Nume:</strong> {{ $produs->nume }}</div>
            <div class="col-md-6 mb-2"><strong>SKU:</strong> {{ $produs->sku ?? '—' }}</div>
            @if($produs->skuAliases->isNotEmpty())
              <div class="col-md-12 mb-2">
                <strong>SKU-uri suplimentare:</strong>
                {{ $produs->skuAliases->pluck('sku')->implode(', ') }}
              </div>
            @endif
            <div class="col-md-6 mb-2"><strong>Cantitate:</strong> {{ $produs->cantitate ?? '-' }}</div>
            <div class="col-md-6 mb-2"><strong>Prag minim stoc:</strong> {{ $produs->prag_minim ?? '-' }}</div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6 mb-2"><strong>Data Procesare:</strong> {{ $produs->data_procesare?->format('d.m.Y') ?? '-' }}</div>
            <div class="col-md-6 mb-2"><strong>Preț:</strong> {{ $produs->pret ?? '-' }}</div>
          </div>
          <div class="row mb-3">
            <div class="col-md-4 mb-2"><strong>Lungime:</strong> {{ $produs->lungime ?? '-' }}</div>
            <div class="col-md-4 mb-2"><strong>Lățime:</strong> {{ $produs->latime ?? '-' }}</div>
            <div class="col-md-4 mb-2"><strong>Grosime:</strong> {{ $produs->grosime ?? '-' }}</div>
          </div>
          <div class="row mb-4">
            <div class="col-md-12">
              <strong>Observații:</strong><br>
              {{ $produs->observatii ?? '-' }}
            </div>
          </div>

          <div class="d-flex justify-content-center">
            <a href="{{ $produs->path('edit') }}" class="btn btn-primary text-white me-3 rounded-3">
              <i class="fa-solid fa-edit me-1"></i> Modifică
            </a>
            <a href="{{ Session::get('returnUrl', route('produse.index')) }}" class="btn btn-secondary rounded-3">
              <i class="fa-solid fa-arrow-left me-1"></i> Înapoi
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
