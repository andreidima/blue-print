<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkUserPermission:users.write')->only(['store', 'update', 'destroy']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $request->session()->forget('returnUrl');

        $searchNume = $request->searchNume;
        $searchTelefon = $request->searchTelefon;
        $today = now((string) config('app.timezone', 'UTC'))->toDateString();

        $users = User::query()
            ->with('roles')
            ->whereDoesntHave('roles', function ($query) use ($today) {
                return $query
                    ->where('slug', 'superadmin')
                    ->where(function ($dateQuery) use ($today) {
                        $dateQuery->whereNull('role_user.starts_at')
                            ->orWhereDate('role_user.starts_at', '<=', $today);
                    })
                    ->where(function ($dateQuery) use ($today) {
                        $dateQuery->whereNull('role_user.ends_at')
                            ->orWhereDate('role_user.ends_at', '>=', $today);
                    });
            })
            ->when($searchNume, function ($query, $searchNume) {
                return $query->where('name', 'like', '%' . $searchNume . '%');
            })
            ->when($searchTelefon, function ($query, $searchTelefon) {
                return $query->where('telefon', 'like', '%' . $searchTelefon . '%');
            })
            ->where('id', '>', 1)
            ->orderBy('activ', 'desc')
            ->orderBy('name')
            ->simplePaginate(25);

        return view('users.index', compact('users', 'searchNume', 'searchTelefon'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $roles = Role::query()
            ->where('slug', '!=', 'superadmin')
            ->orderBy('name')
            ->get();

        return view('users.save', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(UserRequest $request)
    {
        $data = $request->validated();
        $roleIds = $data['roles'] ?? [];
        unset($data['roles'], $data['role_validity_mode'], $data['role_start'], $data['role_end']);

        $data['password'] = Hash::make($data['password']);

        $this->validateRoleIntervals($request, $roleIds);
        $roleSyncPayload = $this->buildRoleSyncPayload($request, $roleIds);

        $user = User::create($data);
        $user->roles()->sync($roleSyncPayload);

        return redirect($request->session()->get('returnUrl', route('users.index')))
            ->with('success', 'Utilizatorul <strong>' . e($user->name) . '</strong> a fost adaugat cu succes!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, User $user)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $user->loadMissing('roles');

        if ($user->isSuperAdmin()) {
            abort(404);
        }

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, User $user)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $user->loadMissing('roles');

        if ($user->isSuperAdmin()) {
            abort(404);
        }

        $roles = Role::query()
            ->where('slug', '!=', 'superadmin')
            ->orderBy('name')
            ->get();

        return view('users.save', compact('user', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(UserRequest $request, User $user)
    {
        $user->loadMissing('roles');

        if ($user->isSuperAdmin()) {
            abort(404);
        }

        $data = $request->validated();
        $roleIds = $data['roles'] ?? [];
        unset($data['roles'], $data['role_validity_mode'], $data['role_start'], $data['role_end']);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $this->validateRoleIntervals($request, $roleIds);
        $roleSyncPayload = $this->buildRoleSyncPayload($request, $roleIds);

        $user->update($data);

        $protectedRolesPayload = $user->roles()
            ->where('slug', 'superadmin')
            ->get()
            ->mapWithKeys(function (Role $role) {
                return [
                    (int) $role->id => [
                        'starts_at' => $role->pivot?->starts_at ?: null,
                        'ends_at' => $role->pivot?->ends_at ?: null,
                    ],
                ];
            })
            ->all();

        $user->roles()->sync($protectedRolesPayload + $roleSyncPayload);

        return redirect($request->session()->get('returnUrl', route('users.index')))
            ->with('status', 'Utilizatorul <strong>' . e($user->name) . '</strong> a fost modificat cu succes!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, User $user)
    {
        $user->loadMissing('roles');

        if ($user->isSuperAdmin()) {
            abort(404);
        }

        $user->delete();

        return back()->with('status', 'Utilizatorul <strong>' . e($user->name) . '</strong> a fost sters cu succes!');
    }

    private function validateRoleIntervals(Request $request, array $roleIds): void
    {
        $roleIds = collect($roleIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($roleIds === []) {
            return;
        }

        $modes = (array) $request->input('role_validity_mode', []);
        $starts = (array) $request->input('role_start', []);
        $ends = (array) $request->input('role_end', []);
        $errors = [];

        foreach ($roleIds as $roleId) {
            $mode = strtolower(trim((string) ($modes[$roleId] ?? 'unlimited')));
            if ($mode !== 'range') {
                continue;
            }

            $startValue = trim((string) ($starts[$roleId] ?? ''));
            $endValue = trim((string) ($ends[$roleId] ?? ''));

            if ($startValue === '') {
                $errors["role_start.$roleId"] = 'Data de inceput este obligatorie pentru interval.';
            }

            if ($endValue === '') {
                $errors["role_end.$roleId"] = 'Data de sfarsit este obligatorie pentru interval.';
            }

            if ($startValue === '' || $endValue === '') {
                continue;
            }

            $startDate = Carbon::parse($startValue)->toDateString();
            $endDate = Carbon::parse($endValue)->toDateString();
            if ($startDate > $endDate) {
                $errors["role_end.$roleId"] = 'Data de sfarsit trebuie sa fie dupa data de inceput.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function buildRoleSyncPayload(Request $request, array $roleIds): array
    {
        $modes = (array) $request->input('role_validity_mode', []);
        $starts = (array) $request->input('role_start', []);
        $ends = (array) $request->input('role_end', []);

        return collect($roleIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->mapWithKeys(function (int $roleId) use ($modes, $starts, $ends) {
                $mode = strtolower(trim((string) ($modes[$roleId] ?? 'unlimited')));
                $isRange = $mode === 'range';
                $startValue = trim((string) ($starts[$roleId] ?? ''));
                $endValue = trim((string) ($ends[$roleId] ?? ''));

                return [
                    $roleId => [
                        'starts_at' => $isRange && $startValue !== '' ? Carbon::parse($startValue)->toDateString() : null,
                        'ends_at' => $isRange && $endValue !== '' ? Carbon::parse($endValue)->toDateString() : null,
                    ],
                ];
            })
            ->all();
    }
}
