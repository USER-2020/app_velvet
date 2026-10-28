<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreserveAgentOAuthIntent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->is('oauth/agent/authorize')) {
            $request->session()->put('agent.oauth.pending_authorize', $request->fullUrl());
        }

        return $next($request);
    }
}
