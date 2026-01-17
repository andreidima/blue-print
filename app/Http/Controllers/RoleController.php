<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $request->session()->forget('returnUrl');

        $search = $request->string('search')->toString();

        $roles = Role::query()
            ->where('slug', '!=', 'superadmin')
            ->when($search, function ($query, $search) {
                return $query->where('name', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->paginate(25);

        $roles->appends(['search' => $search]);

        return view('roles.index', compact('roles', 'search'));
    }

    public function create(Request $request)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('roles.save');
    }

    public function store(RoleRequest $request)
    {
        $data = $request->validated();

        $slugBase = Role::slugFromName($data['name']);
        $slug = $slugBase;

        if ($slug === 'superadmin') {
            return back()->withErrors('Rolul SuperAdmin nu poate fi creat din interfață.');
        }

        $i = 1;
        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $i;
            $i++;
        }

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $slug,
            'color' => $data['color'],
        ]);

        return redirect($request->session()->get('returnUrl', route('roles.index')))
            ->with('success', 'Rolul <strong>' . e($role->name) . '</strong> a fost adăugat cu succes!');
    }

    public function show(Request $request, Role $role)
    {
        if ($role->isSuperAdmin()) {
            abort(404);
        }

        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('roles.show', compact('role'));
    }

    public function edit(Request $request, Role $role)
    {
        if ($role->isSuperAdmin()) {
            abort(404);
        }

        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('roles.save', compact('role'));
    }

    public function update(RoleRequest $request, Role $role)
    {
        if ($role->isSuperAdmin()) {
            abort(404);
        }

        $data = $request->validated();

        $role->update([
            'name' => $data['name'],
            'color' => $data['color'],
        ]);

        return redirect($request->session()->get('returnUrl', route('roles.index')))
            ->with('status', 'Rolul <strong>' . e($role->name) . '</strong> a fost modificat cu succes!');
    }

    public function destroy(Request $request, Role $role)
    {
        if ($role->isSuperAdmin()) {
            abort(404);
        }

        if ($role->users()->exists()) {
            return back()->withErrors('Rolul nu poate fi șters deoarece este atribuit unuia sau mai multor utilizatori.');
        }

        $role->delete();

        return back()->with('status', 'Rolul <strong>' . e($role->name) . '</strong> a fost șters cu succes!');
    }
}

