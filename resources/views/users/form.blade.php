@php
    use Illuminate\Support\Carbon;

    $todayDate = now((string) config('app.timezone', 'UTC'))->toDateString();
    $existingRoleAssignments = isset($user)
        ? $user->roles->where('slug', '!=', 'superadmin')->keyBy('id')
        : collect();

    $selectedRoleIds = collect(old('roles', isset($user) ? $existingRoleAssignments->keys()->all() : []))
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->all();

    $oldModes = old('role_validity_mode', []);
    $oldStarts = old('role_start', []);
    $oldEnds = old('role_end', []);

    $resolveStatus = function (?string $startDate, ?string $endDate) use ($todayDate) {
        if ($startDate !== null && $startDate > $todayDate) {
            return ['label' => 'Programat', 'class' => 'bg-warning text-dark'];
        }

        if ($endDate !== null && $endDate < $todayDate) {
            return ['label' => 'Expirat', 'class' => 'bg-danger'];
        }

        return ['label' => 'Activ', 'class' => 'bg-success'];
    };
@endphp

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

    <div class="col-lg-7 mb-4" data-role-assignments data-today="{{ $todayDate }}">
        <label class="mb-0 ps-3">Roluri<span class="text-danger">*</span></label>
        <div class="bg-white rounded-3 p-2 {{ $errors->has('roles') ? 'border border-danger' : 'border' }}">
            @foreach ($roles as $role)
                @php
                    $roleId = (int) $role->id;
                    $isChecked = in_array($roleId, $selectedRoleIds, true);

                    $existingStart = $existingRoleAssignments->has($roleId)
                        ? (string) optional($existingRoleAssignments->get($roleId)->pivot)->starts_at
                        : '';
                    $existingEnd = $existingRoleAssignments->has($roleId)
                        ? (string) optional($existingRoleAssignments->get($roleId)->pivot)->ends_at
                        : '';

                    $startValue = array_key_exists($roleId, $oldStarts)
                        ? (string) ($oldStarts[$roleId] ?? '')
                        : ($existingStart !== '' ? Carbon::parse($existingStart)->toDateString() : '');
                    $endValue = array_key_exists($roleId, $oldEnds)
                        ? (string) ($oldEnds[$roleId] ?? '')
                        : ($existingEnd !== '' ? Carbon::parse($existingEnd)->toDateString() : '');

                    $defaultMode = ($startValue !== '' || $endValue !== '') ? 'range' : 'unlimited';
                    $modeValue = array_key_exists($roleId, $oldModes)
                        ? (string) ($oldModes[$roleId] ?? 'unlimited')
                        : $defaultMode;
                    if (!in_array($modeValue, ['unlimited', 'range'], true)) {
                        $modeValue = 'unlimited';
                    }

                    $status = $isChecked
                        ? ($modeValue === 'range'
                            ? $resolveStatus($startValue !== '' ? $startValue : null, $endValue !== '' ? $endValue : null)
                            : ['label' => 'Activ', 'class' => 'bg-success'])
                        : ['label' => 'Neatribuit', 'class' => 'bg-secondary'];
                @endphp

                <div class="border rounded-3 p-2 mb-2" data-role-assignment>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div class="form-check mb-0">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="roles[]"
                                id="role_{{ $roleId }}"
                                value="{{ $roleId }}"
                                data-role-toggle
                                {{ $isChecked ? 'checked' : '' }}
                            >
                            <label class="form-check-label" for="role_{{ $roleId }}">
                                <span class="badge" style="background-color: {{ $role->color }}; color: #fff;">
                                    {{ $role->name }}
                                </span>
                            </label>
                        </div>
                        <span class="badge {{ $status['class'] }}" data-role-status>{{ $status['label'] }}</span>
                    </div>

                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="small text-muted mb-1">Valabilitate</label>
                            <select class="form-select form-select-sm" name="role_validity_mode[{{ $roleId }}]" data-role-validity-mode>
                                <option value="unlimited" {{ $modeValue === 'unlimited' ? 'selected' : '' }}>Nelimitat</option>
                                <option value="range" {{ $modeValue === 'range' ? 'selected' : '' }}>Interval X-Y</option>
                            </select>
                        </div>
                        <div class="col-md-8" data-role-range>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="small text-muted mb-1">Data inceput</label>
                                    <input
                                        type="date"
                                        class="form-control form-control-sm {{ $errors->has("role_start.$roleId") ? 'is-invalid' : '' }}"
                                        name="role_start[{{ $roleId }}]"
                                        value="{{ $startValue }}"
                                        data-role-start
                                    >
                                    @if ($errors->has("role_start.$roleId"))
                                        <div class="invalid-feedback">{{ $errors->first("role_start.$roleId") }}</div>
                                    @endif
                                </div>
                                <div class="col-sm-6">
                                    <label class="small text-muted mb-1">Data sfarsit</label>
                                    <input
                                        type="date"
                                        class="form-control form-control-sm {{ $errors->has("role_end.$roleId") ? 'is-invalid' : '' }}"
                                        name="role_end[{{ $roleId }}]"
                                        value="{{ $endValue }}"
                                        data-role-end
                                    >
                                    @if ($errors->has("role_end.$roleId"))
                                        <div class="invalid-feedback">{{ $errors->first("role_end.$roleId") }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
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
            <small class="text-muted">{{ isset($user) ? '(Completati doar pentru modificare)' : '' }}</small>
        </label>
        <input
            id="password"
            type="password"
            class="form-control rounded-3 {{ $errors->has('password') ? 'is-invalid' : '' }}"
            name="password"
            autocomplete="new-password"
            {{ !isset($user) ? 'required' : '' }}
        >
        <div class="form-text ps-3">
            Minim 8 caractere
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <label for="password_confirmation" class="mb-0 ps-3">
            Confirmare parola {!! isset($user) ? '' : '<span class="text-danger">*</span>' !!}
        </label>
        <input
            id="password_confirmation"
            type="password"
            class="form-control rounded-3 {{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}"
            name="password_confirmation"
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
            Renunta
        </a>
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const container = document.querySelector('[data-role-assignments]');
        if (!container) return;

        const today = container.dataset.today || '';

        const resolveStatus = (checked, mode, startValue, endValue) => {
            if (!checked) {
                return { label: 'Neatribuit', className: 'bg-secondary' };
            }

            if (mode !== 'range') {
                return { label: 'Activ', className: 'bg-success' };
            }

            if (startValue && today && startValue > today) {
                return { label: 'Programat', className: 'bg-warning text-dark' };
            }

            if (endValue && today && endValue < today) {
                return { label: 'Expirat', className: 'bg-danger' };
            }

            return { label: 'Activ', className: 'bg-success' };
        };

        container.querySelectorAll('[data-role-assignment]').forEach((row) => {
            const toggle = row.querySelector('[data-role-toggle]');
            const mode = row.querySelector('[data-role-validity-mode]');
            const rangeWrap = row.querySelector('[data-role-range]');
            const start = row.querySelector('[data-role-start]');
            const end = row.querySelector('[data-role-end]');
            const status = row.querySelector('[data-role-status]');

            if (!toggle || !mode || !rangeWrap || !start || !end || !status) {
                return;
            }

            const sync = () => {
                const checked = toggle.checked;
                mode.disabled = !checked;

                const showRange = checked && mode.value === 'range';
                rangeWrap.classList.toggle('d-none', !showRange);
                start.disabled = !showRange;
                end.disabled = !showRange;

                const info = resolveStatus(checked, mode.value, start.value, end.value);
                status.textContent = info.label;
                status.className = `badge ${info.className}`;
            };

            toggle.addEventListener('change', sync);
            mode.addEventListener('change', sync);
            start.addEventListener('change', sync);
            end.addEventListener('change', sync);

            sync();
        });
    });
</script>
