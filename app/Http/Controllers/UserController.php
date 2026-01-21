<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Http\Requests\UserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
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

        $users = User::query()
            ->with('roles')
            ->whereDoesntHave('roles', function ($query) {
                return $query->where('slug', 'superadmin');
            })
            ->when($searchNume, function ($query, $searchNume) {
                return $query->where('name', 'like', '%' . $searchNume . '%');
            })
            ->when($searchTelefon, function ($query, $searchTelefon) {
                return $query->where('telefon', 'like', '%' . $searchTelefon . '%');
            })
            ->where('id', '>', 1) // se sare pentru user 1, Andrei Dima
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
        unset($data['roles']);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        $user->roles()->sync($roleIds);

        return redirect($request->session()->get('returnUrl', route('users.index')))->with('success', 'Utilizatorul <strong>' . e($user->name) . '</strong> a fost adăugat cu succes!');
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
        unset($data['roles']);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        $protectedRoleIds = $user->roles()
            ->where('slug', 'superadmin')
            ->pluck('roles.id')
            ->all();

        $user->roles()->sync(array_values(array_unique(array_merge($protectedRoleIds, $roleIds))));

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

        return back()->with('status', 'Utilizatorul <strong>' . e($user->name) . '</strong> a fost șters cu succes!');
    }
}
