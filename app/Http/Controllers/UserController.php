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
        $searchEmail = $request->searchEmail;
        $roleId = $request->filled('role_id') ? (int) $request->role_id : null;
        $activ = $request->activ;
        $sort = (string) $request->get('sort', 'name');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $currentUser = $request->user();
        $rolesForFilter = $this->availableRolesForFilter($currentUser);
        $allowedRoleIds = $rolesForFilter->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($roleId && !in_array($roleId, $allowedRoleIds, true)) {
            $roleId = null;
        }

        $users = User::query()
            ->with(['roles' => fn ($query) => $query->orderBy('name')])
            ->withoutActiveRoles(['superadmin'])
            ->when(
                !$currentUser?->isSuperAdmin() && !$currentUser?->isAdmin(),
                fn ($query) => $query->withoutActiveRoles(['admin'])
            )
            ->when($searchNume, function ($query, $searchNume) {
                return $query->where('name', 'like', '%' . $searchNume . '%');
            })
            ->when($searchTelefon, function ($query, $searchTelefon) {
                return $query->where('telefon', 'like', '%' . $searchTelefon . '%');
            })
            ->when($searchEmail, function ($query, $searchEmail) {
                return $query->where('email', 'like', '%' . $searchEmail . '%');
            })
            ->when($roleId, function ($query, $roleId) {
                return $query->whereHas('roles', function ($roleQuery) use ($roleId) {
                    $roleQuery->where('roles.id', $roleId);
                });
            })
            ->when($activ !== null && $activ !== '', function ($query) use ($activ) {
                return $query->where('activ', (bool) $activ);
            })
            ->where('id', '>', 1)
            ->when($sort === 'name', fn ($query) => $query->orderBy('name', $dir))
            ->when($sort === 'telefon', fn ($query) => $query->orderBy('telefon', $dir))
            ->when($sort === 'email', fn ($query) => $query->orderBy('email', $dir))
            ->when($sort === 'activ', fn ($query) => $query->orderBy('activ', $dir))
            ->when($sort === 'created_at', fn ($query) => $query->orderBy('created_at', $dir))
            ->when($sort === 'role', function ($query) use ($dir, $currentUser) {
                $query->orderBy(
                    Role::query()
                        ->selectRaw('MIN(roles.name)')
                        ->join('role_user', 'role_user.role_id', '=', 'roles.id')
                        ->whereColumn('role_user.user_id', 'users.id')
                        ->where('roles.slug', '!=', 'superadmin')
                        ->when(
                            !$currentUser?->isAdmin() && !$currentUser?->isSuperAdmin(),
                            fn ($roleQuery) => $roleQuery->where('roles.slug', '!=', 'admin')
                        ),
                    $dir
                );
            })
            ->when(!in_array($sort, ['name', 'telefon', 'email', 'activ', 'created_at', 'role'], true), fn ($query) => $query->orderBy('name'))
            ->orderBy('id')
            ->paginate(25);

        return view('users.index', compact(
            'users',
            'searchNume',
            'searchTelefon',
            'searchEmail',
            'roleId',
            'activ',
            'sort',
            'dir',
            'rolesForFilter'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $this->rememberReturnUrl($request);

        $roles = $this->availableRolesForManager($request->user());

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
        $this->rememberReturnUrl($request);

        $user->loadMissing('roles');

        $this->ensureUserVisibleToManager($request->user(), $user);

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
        $this->rememberReturnUrl($request);

        $user->loadMissing('roles');

        $this->ensureUserVisibleToManager($request->user(), $user);

        $roles = $this->availableRolesForManager($request->user());

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

        $this->ensureUserVisibleToManager($request->user(), $user);

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

        $this->ensureUserVisibleToManager($request->user(), $user);

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

    private function availableRolesForManager(?User $manager)
    {
        return Role::query()
            ->where('slug', '!=', 'superadmin')
            ->when(
                !$manager?->isSuperAdmin(),
                fn ($query) => $query->where('slug', '!=', 'admin')
            )
            ->orderBy('name')
            ->get();
    }

    private function availableRolesForFilter(?User $manager)
    {
        return Role::query()
            ->where('slug', '!=', 'superadmin')
            ->when(
                !$manager?->isSuperAdmin() && !$manager?->isAdmin(),
                fn ($query) => $query->where('slug', '!=', 'admin')
            )
            ->orderBy('name')
            ->get();
    }

    private function ensureUserVisibleToManager(?User $manager, User $target): void
    {
        if ($target->isSuperAdmin()) {
            abort(404);
        }

        if (!$manager?->canSeeUser($target)) {
            abort(404);
        }
    }
}

