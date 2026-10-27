<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Agent\AgentOAuthToken;
use App\Services\Agent\AgentWorkSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // OAuth debe volver al flujo de autorización después del login. Solo
        // aceptamos esta ruta local del propio Master para no convertir la
        // URL intended en un redirect abierto.
        $intended = $request->session()->pull('url.intended');
        $intendedParts = is_string($intended) ? parse_url($intended) : false;
        $intendedHost = $intendedParts['host'] ?? null;
        if (($intendedParts['path'] ?? null) === '/oauth/agent/authorize'
            && ($intendedHost === null || hash_equals($request->getHost(), $intendedHost))) {
            return redirect()->to($intended);
        }

        // El portal inicial debe depender del rol actual. No usamos la URL
        // intended porque una ruta administrativa guardada en sesión
        // podría enviar a un monitor o modelo al panel de reclutamiento.
        $user = $request->user();
        $destination = $user->hasRole('model')
            ? 'model.dashboard'
            : ($user->hasRole('monitor') ? 'monitor.dashboard' : 'admin.recruitment');

        return redirect()->to(route($destination, absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, AgentWorkSessionService $sessions): RedirectResponse
    {
        $installationId = $request->session()->get('agent.oauth.installation_id');
        if (is_string($installationId) && $installationId !== '') {
            $tokens = AgentOAuthToken::query()
                ->whereHas('station', fn ($query) => $query->where('installation_id', strtolower($installationId)))
                ->whereNull('revoked_at')->with('station')->get();
            foreach ($tokens as $token) {
                $sessions->endForStation($token->station_id, 'logout');
                $token->update(['revoked_at' => now()]);
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
