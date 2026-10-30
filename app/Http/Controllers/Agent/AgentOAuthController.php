<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent\AgentOAuthAuthorizationCode;
use App\Models\Agent\AgentOAuthToken;
use App\Models\Agent\AgentStation;
use App\Services\Agent\AgentWorkSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentOAuthController extends Controller
{
    public function authorize(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'in:velvet-agent'],
            'redirect_uri' => ['required', 'string', 'max:500'],
            'response_type' => ['required', 'string', 'in:code'],
            'installation_id' => ['required', 'uuid'],
            'hostname' => ['required', 'string', 'max:255'],
            'studio_id' => ['required', 'string', 'max:100'],
            'code_challenge' => ['required', 'string', 'min:43', 'max:128'],
            'code_challenge_method' => ['required', 'string', 'in:S256'],
            'state' => ['nullable', 'string', 'max:512'],
        ]);

        abort_unless(in_array($data['studio_id'], config('agent.studios'), true), 422, 'Invalid studio.');
        abort_unless($this->validLoopbackRedirect($data['redirect_uri']), 422, 'Invalid redirect URI.');

        $station = AgentStation::where('installation_id', strtolower($data['installation_id']))->first();
        $canEnroll = $request->user()->hasPermissionTo('stations.enroll');
        if (! $canEnroll) {
            abort_unless(
                $request->user()->hasPermissionTo('stations.connect')
                    && $station
                    && ! $station->revoked_at
                    && $station->studio_id === $data['studio_id'],
                403,
                'The station must be enrolled by an administrator before a model can connect.'
            );
        }

        // La sesión web queda vinculada a la estación que está autorizando.
        // Esto permite que el logout de la modelo revoque solo esa estación.
        $request->session()->put('agent.oauth.installation_id', strtolower($data['installation_id']));

        $code = 'vac_'.Str::random(64);
        AgentOAuthAuthorizationCode::create([
            'code_hash' => hash('sha256', $code),
            'user_id' => $request->user()->id,
            'installation_id' => strtolower($data['installation_id']),
            'hostname' => $data['hostname'],
            'studio_id' => $data['studio_id'],
            'redirect_uri' => $data['redirect_uri'],
            'code_challenge' => $data['code_challenge'],
            'code_challenge_method' => $data['code_challenge_method'],
            'expires_at' => now()->addMinutes(config('agent.oauth.authorization_code_minutes')),
        ]);

        $request->session()->forget('agent.oauth.pending_authorize');

        return redirect()->away($this->withQuery($data['redirect_uri'], [
            'code' => $code,
            ...(! empty($data['state']) ? ['state' => $data['state']] : []),
        ]));
    }

    public function token(Request $request)
    {
        $data = $request->validate([
            'grant_type' => ['required', 'string', 'in:authorization_code,refresh_token'],
            'client_id' => ['required', 'string', 'in:velvet-agent'],
            'code' => ['required_if:grant_type,authorization_code', 'nullable', 'string', 'max:256'],
            'redirect_uri' => ['required_if:grant_type,authorization_code', 'nullable', 'string', 'max:500'],
            'code_verifier' => ['required_if:grant_type,authorization_code', 'nullable', 'string', 'min:43', 'max:128'],
            'refresh_token' => ['required_if:grant_type,refresh_token', 'nullable', 'string', 'max:256'],
        ]);

        return $data['grant_type'] === 'authorization_code'
            ? $this->exchangeCode($data)
            : $this->refresh($data['refresh_token']);
    }

    public function revoke(Request $request, AgentWorkSessionService $sessions)
    {
        $oauth = $request->attributes->get('agentOAuthToken');
        $oauth->update(['revoked_at' => now()]);
        $sessions->endForStation($oauth->station_id, 'revoked', 'revoked');

        return response()->noContent();
    }

    public function me(Request $request)
    {
        $user = $request->attributes->get('agentUser');
        $station = $request->attributes->get('agentStation');
        $permissions = $user->getAllPermissions()->pluck('slug')->values()->all();
        $canConfigure = $user->hasPermissionTo('stations.configure');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('slug')->values()->all(),
                'permissions' => $permissions,
            ],
            'modules' => [
                'home' => true,
                'monitoring' => true,
                'obs' => true,
                'diagnostics' => true,
                'configuration' => $canConfigure,
            ],
            'station' => $this->stationPayload($station),
        ]);
    }

    public function station(Request $request)
    {
        return response()->json($this->stationPayload($request->attributes->get('agentStation')));
    }

    private function exchangeCode(array $data)
    {
        abort_unless($this->validLoopbackRedirect($data['redirect_uri']), 400, 'Invalid redirect URI.');
        $verifierHash = rtrim(strtr(base64_encode(hash('sha256', $data['code_verifier'], true)), '+/', '-_'), '=');

        return DB::transaction(function () use ($data, $verifierHash) {
            $authorization = AgentOAuthAuthorizationCode::where('code_hash', hash('sha256', $data['code']))->lockForUpdate()->first();
            abort_unless($authorization && ! $authorization->used_at && $authorization->expires_at->isFuture(), 400, 'Invalid authorization code.');
            abort_unless(hash_equals($authorization->redirect_uri, $data['redirect_uri']), 400, 'Redirect URI mismatch.');
            abort_unless(hash_equals($authorization->code_challenge, $verifierHash), 400, 'PKCE verification failed.');
            $authorization->update(['used_at' => now()]);

            $station = AgentStation::where('installation_id', strtolower($authorization->installation_id))->lockForUpdate()->first();
            abort_if($station && $station->studio_id !== $authorization->studio_id, 409, 'Station belongs to another studio.');
            if (! $station) {
                $station = AgentStation::create([
                    'installation_id' => strtolower($authorization->installation_id),
                    'hostname' => $authorization->hostname,
                    'studio_id' => $authorization->studio_id,
                    'credential_hash' => hash('sha256', 'oauth-device-'.Str::random(64)),
                    'abilities' => ['agent:heartbeat', 'agent:configuration', 'agent:events', 'agent:results'],
                    'configuration' => ['masterUrl' => config('agent.master_url'), 'studioId' => $authorization->studio_id, 'heartbeatSeconds' => config('agent.heartbeat_seconds'), 'development' => false],
                ]);
            }

            return $this->issueTokens($station, $authorization->user_id);
        });
    }

    private function refresh(string $refreshToken)
    {
        return DB::transaction(function () use ($refreshToken) {
            $oauth = AgentOAuthToken::where('refresh_token_hash', hash('sha256', $refreshToken))->lockForUpdate()->first();
            abort_unless($oauth && ! $oauth->revoked_at && $oauth->refresh_expires_at->isFuture(), 400, 'Invalid refresh token.');
            $oauth->update(['revoked_at' => now()]);

            return $this->issueTokens($oauth->station, $oauth->user_id);
        });
    }

    private function issueTokens(AgentStation $station, int $userId)
    {
        $access = 'vea_access_'.Str::random(64);
        $refresh = 'vea_refresh_'.Str::random(64);
        $accessExpires = now()->addMinutes(config('agent.oauth.access_token_minutes'));
        $refreshExpires = now()->addDays(config('agent.oauth.refresh_token_days'));
        AgentOAuthToken::create([
            'station_id' => $station->id,
            'user_id' => $userId,
            'access_token_hash' => hash('sha256', $access),
            'refresh_token_hash' => hash('sha256', $refresh),
            'access_expires_at' => $accessExpires,
            'refresh_expires_at' => $refreshExpires,
        ]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_in' => max(1, now()->diffInSeconds($accessExpires)),
            'refresh_expires_in' => max(1, now()->diffInSeconds($refreshExpires)),
            'station' => $this->stationPayload($station),
        ]);
    }

    private function stationPayload(AgentStation $station): array
    {
        return ['id' => $station->id, 'installationId' => $station->installation_id, 'hostname' => $station->hostname, 'studioId' => $station->studio_id, 'abilities' => $station->abilities];
    }

    private function validLoopbackRedirect(string $uri): bool
    {
        $parts = parse_url($uri);
        return in_array($parts['scheme'] ?? null, ['http'], true)
            && in_array($parts['host'] ?? null, ['127.0.0.1', 'localhost'], true)
            && isset($parts['port']) && $parts['port'] >= 1024 && $parts['port'] <= 65535
            && ($parts['path'] ?? '') === '/oauth/callback'
            && empty($parts['user']) && empty($parts['pass']) && empty($parts['fragment']);
    }

    private function withQuery(string $uri, array $query): string
    {
        return $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
