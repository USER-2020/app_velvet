<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // A newly-created in-memory Eloquent model may not yet contain the DB
        // default. Only an explicit false represents a disabled account.
        if (! $request->user() || $request->user()->getAttribute('is_active') !== false) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Esta cuenta ya no tiene acceso activo.',
        ]);
    }
}
