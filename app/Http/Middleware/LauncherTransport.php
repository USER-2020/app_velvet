<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LauncherTransport
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        abort_unless($request->isSecure(), 400, 'HTTPS is required.');
        abort_if(strlen($request->getContent()) > config('launcher.max_payload_bytes'), 413, 'Payload too large.');
        abort_if(($request->isMethod('POST') || $request->isMethod('PUT')) && ! $request->isJson(), 400, 'JSON is required.');

        return $next($request);
    }
}
