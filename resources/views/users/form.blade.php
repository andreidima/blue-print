<div class="row mb-4 pt-2 rounded-3" style="border:1px solid #e9ecef; border-left:0.25rem darkcyan solid; background-color:rgb(241, 250, 250)">
    <div class="col-lg-6 mb-4">
        <label for="name" class="mb-0 ps-3">Nume<span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('name') ? 'is-invalid' : '' }}"
            name="name"
            id="name"
            value="{{ old('name', $user->name ?? '') }}"
            autocomplete="name"
            required>
    </div>
    <div class="col-lg-6 mb-4">
        <label for="telefon" class="mb-0 ps-3">Telefon</label>
        <input
            type="text"
            class="form-control bg-white rounded-3 {{ $errors->has('telefon') ? 'is-invalid' : '' }}"
            name="telefon"
            id="telefon"
            value="{{ old('telefon', $user->telefon ?? '') }}">
    </div>
    <div class="col-lg-5 mb-4">
        <label for="email" class="mb-0 ps-3">Email<span class="text-danger">*</span></label>
        <input
            type="email"
            class="form-control bg-white rounded-3 {{ $errors->has('email') ? 'is-invalid' : '' }}"
            name="email"
            id="email"
            autocomplete="email"
            value="{{ old('email', $user->email ?? '') }}"
            required>
    </div>
    <div class="col-lg-4 mb-4">
        @php
            $selectedRoleIds = collect(old('roles', isset($user) ? $user->roles->where('slug', '!=', 'superadmin')->pluck('id')->all() : []))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();
        @endphp

        <label class="mb-0 ps-3">Roluri<span class="text-danger">*</span></label>
        <div class="bg-white rounded-3 p-2 {{ $errors->has('roles') ? 'border border-danger' : 'border' }}">
            @foreach ($roles as $role)
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="roles[]"
                        id="role_{{ $role->id }}"
                        value="{{ $role->id }}"
                        {{ in_array($role->id, $selectedRoleIds, true) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="role_{{ $role->id }}">
                        <span class="badge" style="background-color: {{ $role->color }}; color: #fff;">
                            {{ $role->name }}
                        </span>
                    </label>
                </div>
            @endforeach
        </div>
        @if ($errors->has('roles'))
            <div class="text-danger small mt-1 ps-3">{{ $errors->first('roles') }}</div>
        @endif
    </div>
    <div class="col-lg-3 mb-4 text-center">
        <fieldset class="mb-4">
            <legend class="mb-0 fs-6">Cont activ<span class="text-danger">*</span></legend>
            <div class="d-flex py-1 justify-content-center">
                <div class="form-check me-4">
                    <input class="form-check-input" type="radio" value="1" name="activ" id="activ_da"
                        {{ old('activ', $user->activ ?? '') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="activ_da">DA</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" value="0" name="activ" id="activ_nu"
                        {{ old('activ', $user->activ ?? '') == '0' ? 'checked' : '' }}>
                    <label class="form-check-label" for="activ_nu">NU</label>
                </div>
            </div>
        </fieldset>
    </div>
    <div class="col-lg-6 mb-4">
        <label for="password" class="mb-0 ps-3">
            Parola {!! isset($user) ? '' : '<span class="text-danger">*</span>' !!}
            <small class="text-muted">{{ isset($user) ? '(Completați doar pentru modificare)' : '' }}</small>
        </label>
        <input
            id="password"
            type="password"
            class="form-control rounded-3 {{ $errors->has('password') ? 'is-invalid' : '' }}"
            name="password"
            id="password"
            autocomplete="new-password"
            {{ !isset($user) ? 'required' : '' }}
        >
        <div class="form-text ps-3">
            Minim 8 caractere
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <label for="password_confirmation" class="mb-0 ps-3">
            Confirmare parolă {!! isset($user) ? '' : '<span class="text-danger">*</span>' !!}
        </label>
        <input
            id="password_confirmation"
            type="password"
            class="form-control rounded-3 {{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}"
            name="password_confirmation"
            id="password_confirmation"
            autocomplete="new-password"
            {{ !isset($user) ? 'required' : '' }}
        >
    </div>
</div>

<div class="row">
    <div class="col-lg-12 mb-2 d-flex justify-content-center">
        <button type="submit" class="btn btn-primary text-white me-3 rounded-3">
            <i class="fa-solid fa-save me-1"></i> {{ $buttonText }}
        </button>
        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('users.index')) }}">
            Renunță
        </a>
    </div>
</div>
