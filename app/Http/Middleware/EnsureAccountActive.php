<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $user = $request->user()->fresh();

            if (! $user || $user->status !== 'active') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                abort(403, 'Esta cuenta no tiene acceso activo.');
            }

            Auth::setUser($user);
        }

        return $next($request);
    }
}
