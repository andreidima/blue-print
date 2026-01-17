<?php

namespace App\Http\Controllers\Tech;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ImpersonationController extends Controller
{
    public function index(Request $request): View
    {
        $searchNume = $request->string('searchNume')->toString();
        $searchTelefon = $request->string('searchTelefon')->toString();

        $users = User::query()
            ->with('roles')
            ->when($searchNume, function ($query, $searchNume) {
                return $query->where('name', 'like', '%' . $searchNume . '%');
            })
            ->when($searchTelefon, function ($query, $searchTelefon) {
                return $query->where('telefon', 'like', '%' . $searchTelefon . '%');
            })
            ->where('id', '>', 1)
            ->orderByDesc('activ')
            ->orderBy('name')
            ->simplePaginate(25);

        $users->appends([
            'searchNume' => $searchNume,
            'searchTelefon' => $searchTelefon,
        ]);

        return view('tech.impersonare.index', [
            'users' => $users,
            'searchNume' => $searchNume,
            'searchTelefon' => $searchTelefon,
            'currentUserId' => Auth::id(),
        ]);
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $currentUser = Auth::user();

        if (!$currentUser || !$currentUser->isSuperAdmin()) {
            abort(403);
        }

        if ($user->id === $currentUser->id) {
            return back()->withErrors('Nu poți impersona propriul cont.');
        }

        if (!$user->activ) {
            return back()->withErrors('Nu poți impersona un cont inactiv.');
        }

        if (!$request->session()->has('impersonator_id')) {
            $request->session()->put('impersonator_id', $currentUser->id);
            $request->session()->put('impersonator_name', $currentUser->name);
        }

        Auth::login($user);

        return redirect()->route('acasa')->with(
            'status',
            'Acum impersonați contul <strong>' . e($user->name) . '</strong>.'
        );
    }

    public function stop(Request $request): RedirectResponse
    {
        if (!$request->session()->has('impersonator_id')) {
            return redirect()->route('acasa');
        }

        $originalId = $request->session()->pull('impersonator_id');
        $originalName = $request->session()->pull('impersonator_name');

        $originalUser = $originalId ? User::find($originalId) : null;

        if ($originalUser) {
            Auth::login($originalUser);
            $message = 'Ați revenit la contul <strong>' . e($originalUser->name) . '</strong>.';
        } else {
            Auth::logout();
            $displayName = $originalName ? ' (' . e($originalName) . ')' : '';
            $message = 'Sesiunea de impersonare a fost oprită, dar contul inițial' . $displayName . ' nu a mai fost găsit.';
        }

        return redirect()->route('acasa')->with('status', $message);
    }
}
