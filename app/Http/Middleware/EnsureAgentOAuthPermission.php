<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentOAuthPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->hasPermissionTo('stations.enroll') || $user?->hasPermissionTo('stations.connect'),
            403
        );

        return $next($request);
    }
}
