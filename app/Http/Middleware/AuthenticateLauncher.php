<?php

namespace App\Http\Middleware;

use App\Models\LauncherStation;
use Closure;
use Illuminate\Http\Request;

class AuthenticateLauncher
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $station = $token ? LauncherStation::where('credential_hash', hash('sha256', $token))->first() : null;

        abort_unless($station && ! $station->revoked_at, 401, 'Launcher credential is invalid or revoked.');
        $request->attributes->set('launcherStation', $station);

        return $next($request);
    }
}
