<?php

namespace App\Http\Middleware\Agent;

use Closure;
use Illuminate\Http\Request;

class AgentTransport
{
    public function handle(Request $request, Closure $next)
    {
        // Always JSON, including validation/auth/throttle errors.
        $request->headers->set('Accept', 'application/json');
        abort_unless($request->isSecure(), 400, 'HTTPS is required.');
        abort_if(strlen($request->getContent()) > config('agent.max_payload_bytes'), 413, 'Payload too large.');
        $oauthForm = $request->isMethod('POST') && ($request->is('api/agent/oauth/token') || $request->is('api/agent/oauth/revoke'));
        if (($request->isMethod('POST') || $request->isMethod('PUT')) && ! $oauthForm) {
            abort_unless($request->isJson(), 400, 'JSON is required.');
        }

        return $next($request);
    }
}
