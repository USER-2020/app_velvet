<?php

namespace App\Http\Middleware\Agent;

use App\Models\Agent\AgentOAuthToken;
use Closure;
use Illuminate\Http\Request;

class AuthenticateAgentOAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) <= 256, 401, 'Invalid OAuth access token.');

        $oauth = AgentOAuthToken::with(['station', 'user'])
            ->where('access_token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->first();

        abort_unless($oauth && $oauth->access_expires_at->isFuture(), 401, 'OAuth access token expired.');
        abort_unless($oauth->station && ! $oauth->station->revoked_at, 401, 'Station is revoked.');
        abort_unless($oauth->user && $oauth->user->is_active, 401, 'User is inactive.');

        $oauth->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('agentOAuthToken', $oauth);
        $request->attributes->set('agentStation', $oauth->station);
        $request->attributes->set('agentUser', $oauth->user);

        return $next($request);
    }
}
