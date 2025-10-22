@extends('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0 0;">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-box me-1"></i>
                Produse
            </span>
        </div>

        {{-- Search form --}}
        <div class="col-lg-6">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                @csrf
                <div class="row mb-2 custom-search-form justify-content-center">
                    <div class="col-lg-5">
                        <input
                            type="text"
                            class="form-control rounded-3"
                            id="searchNume"
                            name="searchNume"
                            placeholder="Nume"
                            value="{{ $searchNume }}">
                    </div>
                    <div class="col-lg-5">
                        <select
                            name="searchCategorie"
                            id="searchCategorie"
                            class="form-control rounded-3">
                            <option value="">Toate categoriile</option>
                            @foreach($allCategorii as $categorie)
                                <option
                                    value="{{ $categorie->id }}"
                                    {{ $searchCategorie == $categorie->id ? 'selected' : '' }}>
                                    {{ $categorie->nume }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row custom-search-form justify-content-center">
                    <div class="col-lg-4 mb-2">
                        <button
                            class="btn btn-sm w-100 btn-primary text-white border border-dark rounded-3"
                            type="submit">
                            <i class="fas fa-search me-1"></i>Caută
                        </button>
                    </div>
                    <div class="col-lg-4 mb-2">
                        <a
                            class="btn btn-sm w-100 btn-secondary text-white border border-dark rounded-3"
                            href="{{ url()->current() }}"
                            role="button">
                            <i class="far fa-trash-alt me-1"></i>Resetează
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- Add button --}}
        <div class="col-lg-3 text-end">
            <a
                class="btn btn-sm btn-success text-white border border-dark rounded-3 col-md-8"
                href="{{ route('produse.create') }}">
                <i class="fas fa-plus-square text-white"></i> Adaugă Produs
            </a>
        </div>
    </div>

    {{-- Card Body --}}
    <div class="card-body px-0 py-3">
        @include('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded">
                <thead class="text-white">
                    <tr>
                        <th class="text-white culoare2"><i class="fa-solid fa-hashtag"></i></th>
                        <th class="text-white culoare2"><i class="fa-solid fa-box me-1"></i> Nume</th>
                        <th class="text-white culoare2"><i class="fa-solid fa-barcode me-1"></i> SKU</th>
                        <th class="text-white culoare2"><i class="fa-solid fa-tags me-1"></i> Categorie</th>
                        <th class="text-white culoare2"><i class="fa-solid fa-layer-group me-1"></i> Cantitate</th>
                        <th class="text-white culoare2"><i class="fa-solid fa-calendar-days me-1"></i> Data</th>
                        <th class="text-white culoare2"><i class="fa-solid fa-euro-sign me-1"></i> Preț</th>

                        {{-- QR Code column --}}
                        <th class="text-white culoare2"><i class="fa-solid fa-qrcode me-1"></i> QR</th>

                        <th class="text-white culoare2 text-end"><i class="fa-solid fa-cogs me-1"></i> Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($produse as $produs)
                        <tr @class([
                            'bg-danger' => $produs->cantitate <= $produs->prag_minim,
                        ])>
                            <td>{{ ($produse->currentPage() - 1) * $produse->perPage() + $loop->index + 1 }}</td>
                            <td>{{ $produs->nume }}</td>
                            <td>{{ $produs->sku ?? '—' }}</td>
                            <td>{{ $produs?->categorie->nume }}</td>
                            <td>{{ $produs->cantitate ?? '-' }}</td>
                            <td>{{ $produs->data_procesare?->format('d.m.Y') ?? '-' }}</td>
                            <td>{{ $produs->pret ?? '-' }}</td>

                            {{-- QR button --}}
                            <td>
                                <a
                                href="{{ route('produse.eticheta', $produs) }}"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-sm bg-primary text-white"
                                title="Generează QR Code">
                                <i class="fa-solid fa-qrcode"></i>
                                </a>
                            </td>

                            <td>
                                <div class="d-flex justify-content-end">
                                    {{-- QR Code button --}}
                                    {{-- <a
                                    href="{{ route('produse.eticheta', $produs) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="btn btn-sm bg-primary text-white me-1"
                                    title="Generează QR Code">
                                    <i class="fa-solid fa-qrcode"></i>
                                    </a> --}}

                                    {{-- View --}}
                                    <a
                                        href="{{ $produs->path() }}"
                                        class="btn btn-sm bg-success text-white me-1"
                                        title="Vizualizează">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>

                                    {{-- Edit --}}
                                    <a
                                        href="{{ $produs->path('edit') }}"
                                        class="btn btn-sm bg-primary text-white me-1"
                                        title="Modifică">
                                        <i class="fa-solid fa-edit"></i>
                                    </a>

                                    {{-- Delete --}}
                                    <button
                                        type="button"
                                        class="btn btn-sm bg-danger text-white"
                                        data-bs-toggle="modal"
                                        data-bs-target="#stergeProdus{{ $produs->id }}"
                                        title="Șterge">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-3">
                                <i class="fa-solid fa-exclamation-circle me-1"></i>
                                Nu s-au găsit produse.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav>
            <ul class="pagination justify-content-center">
                {{ $produse->withQueryString()->links() }}
            </ul>
        </nav>
    </div>
</div>

{{-- Modals to delete products --}}
@foreach($produse as $produs)
    <div
        class="modal fade text-dark"
        id="stergeProdus{{ $produs->id }}"
        tabindex="-1"
        role="dialog"
        aria-labelledby="stergeProdusLabel{{ $produs->id }}"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5
                        class="modal-title text-white"
                        id="stergeProdusLabel{{ $produs->id }}">
                        <i class="fa-solid fa-trash me-1"></i> Produs: {{ $produs->nume }}
                    </h5>
                    <button
                        type="button"
                        class="btn-close bg-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>
                </div>
                <div class="modal-body text-start">
                    Ești sigur că vrei să ștergi acest produs?
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Renunță
                    </button>
                    <form
                        method="POST"
                        action="{{ $produs->path('destroy') }}">
                        @method('DELETE')
                        @csrf
                        <button
                            type="submit"
                            class="btn btn-danger text-white">
                            <i class="fa-solid fa-trash me-1"></i> Șterge Produs
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection
