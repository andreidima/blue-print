<div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
    <div class="col-lg-6 mb-4">
        <label for="nume" class="mb-0 ps-3">Nume<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('nume') ? 'is-invalid' : '' }}"
            name="nume"
            id="nume"
            value="{{ old('nume', $client->nume ?? '') }}"
            required>
    </div>
    <div class="col-lg-6 mb-4">
        <label for="prenume" class="mb-0 ps-3">Prenume<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('prenume') ? 'is-invalid' : '' }}"
            name="prenume"
            id="prenume"
            value="{{ old('prenume', $client->prenume ?? '') }}"
            required>
    </div>
    <div class="col-lg-6 mb-4">
        <label for="telefon" class="mb-0 ps-3">Telefon</label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('telefon') ? 'is-invalid' : '' }}"
            name="telefon"
            id="telefon"
            value="{{ old('telefon', $client->telefon ?? '') }}">
    </div>
    <div class="col-lg-6 mb-4">
        <label for="email" class="mb-0 ps-3">Email</label>
        <input
            type="email"
            class="form-control bg-white rounded-3 {{ $errors->has('email') ? 'is-invalid' : '' }}"
            name="email"
            id="email"
            value="{{ old('email', $client->email ?? '') }}">
    </div>
    <div class="col-lg-12 mb-4">
        <label for="adresa" class="mb-0 ps-3">Adresa</label>
        <textarea
            class="form-control bg-white rounded-3 {{ $errors->has('adresa') ? 'is-invalid' : '' }}"
            name="adresa"
            id="adresa"
            rows="3">{{ old('adresa', $client->adresa ?? '') }}</textarea>
    </div>
</div>

<div class="row">
    <div class="col-lg-12 mb-2 d-flex justify-content-center">
        <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
            <i class="fa-solid fa-save me-1"></i> {{ $buttonText }}
        </button>
        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('clienti.index')) }}">
            Renunta
        </a>
    </div>
</div>
