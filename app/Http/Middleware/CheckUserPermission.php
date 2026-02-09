<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckUserPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ... $permissions): Response
    {
        if (!$user = Auth::user()) {
            return redirect()->route('login');
        }

        if (!$user->hasAnyPermission($permissions)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
