<?php

namespace App\Http\Middleware\Agent;

use App\Models\Agent\AgentStation;
use App\Models\Agent\AgentOAuthToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

class AuthenticateAgent
{
    public function handle(Request $request, Closure $next, string $ability)
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) <= 256, 401, 'Invalid device credential.');
        $hash = hash('sha256', $token);
        $station = AgentStation::where('credential_hash', $hash)->first();
        $oauth = null;
        if (! $station) {
            $oauth = AgentOAuthToken::with(['station', 'user'])->where('access_token_hash', $hash)->first();
            $station = $oauth?->station;
            abort_unless($oauth && ! $oauth->revoked_at && $oauth->access_expires_at->isFuture() && $oauth->user?->is_active, 401, 'Invalid OAuth access token.');
            $oauth->forceFill(['last_used_at' => now()])->save();
        }
        $this->authorize($station, $ability);
        $request->attributes->set('agentStation', $station);
        if ($oauth) {
            $request->attributes->set('agentOAuthToken', $oauth);
            $request->attributes->set('agentUser', $oauth->user);
        }

        // Count requests outside the business transaction, even with a database
        // cache store: validation failures must not roll rate-limit counters back.
        return app(ThrottleRequests::class)->handle($request, function () use ($request, $next, $ability, $hash, $station, $oauth) {
            return DB::transaction(function () use ($request, $next, $ability, $hash, $station, $oauth) {
                $current = AgentStation::whereKey($station->id)->lockForUpdate()->first();
                $this->authorize($current, $ability);
                if ($oauth) {
                    $currentOAuth = AgentOAuthToken::whereKey($oauth->id)->lockForUpdate()->first();
                    abort_unless($currentOAuth && ! $currentOAuth->revoked_at && $currentOAuth->access_expires_at->isFuture(), 401, 'Invalid OAuth access token.');
                    abort_unless(hash_equals($currentOAuth->access_token_hash, $hash), 401, 'Invalid OAuth access token.');
                    $currentOAuth->update(['last_used_at' => now()]);
                    $request->attributes->set('agentOAuthToken', $currentOAuth);
                } else {
                    abort_unless(hash_equals($current->credential_hash, $hash), 401, 'Invalid device credential.');
                }
                $request->attributes->set('agentStation', $current);

                return $next($request);
            }, 3);
        }, 'agent-station');
    }

    private function authorize(?AgentStation $station, string $ability): void
    {
        abort_unless($station && ! $station->revoked_at, 401, 'Invalid device credential.');
        abort_unless(in_array('agent:'.$ability, $station->abilities, true), 403, 'Insufficient device ability.');
    }
}
