<?php

namespace Tests\Feature;

use App\Models\Agent\AgentOAuthToken;
use App\Models\Agent\AgentStation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VelvetMasterOperationalPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgentOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, VelvetMasterOperationalPermissionsSeeder::class]);
        config(['agent.studios' => ['studio-bogota'], 'agent.master_url' => 'https://master.test']);
    }

    public function test_agent_can_authorize_in_browser_exchange_pkce_code_and_use_session_token(): void
    {
        $user = User::factory()->create()->assignRole('admin');
        $installation = (string) Str::uuid();
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $redirect = 'http://127.0.0.1:43127/oauth/callback';

        $authorization = $this->actingAs($user)->get('/oauth/agent/authorize?'.http_build_query([
            'client_id' => 'velvet-agent', 'redirect_uri' => $redirect, 'response_type' => 'code',
            'installation_id' => $installation, 'hostname' => 'studio-mac', 'studio_id' => 'studio-bogota',
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256', 'state' => 'state-123',
        ]));
        $authorization->assertRedirect();
        $callback = parse_url($authorization->headers->get('Location'));
        parse_str($callback['query'] ?? '', $query);
        $this->assertSame('state-123', $query['state']);

        $tokens = $this->post('https://localhost/api/agent/oauth/token', [
            'grant_type' => 'authorization_code', 'client_id' => 'velvet-agent', 'code' => $query['code'],
            'redirect_uri' => $redirect, 'code_verifier' => $verifier,
        ], ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'])->assertOk()->json();

        $this->assertSame('Bearer', $tokens['token_type']);
        $this->assertNotEmpty($tokens['access_token']);
        $this->assertNotEmpty($tokens['refresh_token']);
        $this->assertDatabaseHas('agent_stations', ['installation_id' => $installation, 'studio_id' => 'studio-bogota']);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokens['access_token'])
            ->getJson('https://localhost/api/agent/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('station.installationId', $installation)
            ->assertJsonPath('modules.configuration', true);
        $this->assertContains('stations.enroll', $response->json('user.permissions'));
    }

    public function test_login_preserves_master_session_and_continues_agent_authorization(): void
    {
        $user = User::factory()->create()->assignRole('admin');
        $installation = (string) Str::uuid();
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $query = http_build_query([
            'client_id' => 'velvet-agent', 'redirect_uri' => 'http://127.0.0.1:43129/oauth/callback',
            'response_type' => 'code', 'installation_id' => $installation, 'hostname' => 'station-login',
            'studio_id' => 'studio-bogota', 'code_challenge' => $challenge,
            'code_challenge_method' => 'S256', 'state' => 'state-login',
        ]);

        $this->get('/oauth/agent/authorize?'.$query)->assertRedirect(route('login'));

        $login = $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $login->assertRedirect();
        $loginLocation = parse_url($login->headers->get('Location'));
        parse_str($loginLocation['query'] ?? '', $loginQuery);
        $this->assertSame('/oauth/agent/authorize', $loginLocation['path'] ?? null);
        $this->assertSame('velvet-agent', $loginQuery['client_id'] ?? null);
        $this->assertSame($installation, $loginQuery['installation_id'] ?? null);

        $authorization = $this->get($login->headers->get('Location'));
        $authorization->assertRedirect();
        parse_str(parse_url($authorization->headers->get('Location'))['query'] ?? '', $callback);

        $this->assertAuthenticatedAs($user);
        $this->assertArrayHasKey('code', $callback);
        $this->assertSame('state-login', $callback['state']);
    }

    public function test_model_can_connect_to_an_existing_station_but_cannot_enroll_one(): void
    {
        $model = User::factory()->create()->assignRole('model');
        $installation = (string) Str::uuid();
        AgentStation::create([
            'installation_id' => $installation,
            'hostname' => 'station-enrolled',
            'studio_id' => 'studio-bogota',
            'credential_hash' => hash('sha256', 'device-secret'),
            'abilities' => ['agent:heartbeat'],
            'configuration' => ['masterUrl' => 'https://master.test', 'studioId' => 'studio-bogota', 'heartbeatSeconds' => 30, 'development' => false],
        ]);
        $query = [
            'client_id' => 'velvet-agent', 'redirect_uri' => 'http://127.0.0.1:43130/oauth/callback',
            'response_type' => 'code', 'installation_id' => $installation, 'hostname' => 'station-enrolled',
            'studio_id' => 'studio-bogota', 'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ];

        $this->actingAs($model)->get('/oauth/agent/authorize?'.http_build_query($query))->assertRedirect();

        $query['installation_id'] = (string) Str::uuid();
        $this->actingAs($model)->get('/oauth/agent/authorize?'.http_build_query($query))->assertForbidden();
    }

    public function test_model_receives_operational_modules_without_configuration_access(): void
    {
        $model = User::factory()->create()->assignRole('model');
        $installation = (string) Str::uuid();
        AgentStation::create([
            'installation_id' => $installation,
            'hostname' => 'station-model',
            'studio_id' => 'studio-bogota',
            'credential_hash' => hash('sha256', 'device-model'),
            'abilities' => ['agent:heartbeat'],
            'configuration' => ['masterUrl' => 'https://master.test', 'studioId' => 'studio-bogota', 'heartbeatSeconds' => 30, 'development' => false],
        ]);

        [$tokens] = $this->authorizeAgentFor($model, $installation);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokens['access_token'])
            ->getJson('https://localhost/api/agent/me')
            ->assertOk()
            ->assertJsonPath('modules.home', true)
            ->assertJsonPath('modules.monitoring', true)
            ->assertJsonPath('modules.obs', true)
            ->assertJsonPath('modules.diagnostics', true)
            ->assertJsonPath('modules.configuration', false);
        $this->assertContains('stations.connect', $response->json('user.permissions'));
    }

    public function test_authorization_code_is_one_time_and_refresh_rotates(): void
    {
        [$tokens, $user] = $this->authorizeAgent();
        $station = AgentStation::firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$tokens['access_token'])
            ->getJson('https://localhost/api/agent/station/configuration')->assertOk();

        $refresh = $this->post('https://localhost/api/agent/oauth/token', [
            'grant_type' => 'refresh_token', 'client_id' => 'velvet-agent', 'refresh_token' => $tokens['refresh_token'],
        ], ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'])->assertOk()->json();
        $this->assertNotSame($tokens['access_token'], $refresh['access_token']);

        $this->withHeader('Authorization', 'Bearer '.$tokens['access_token'])
            ->getJson('https://localhost/api/agent/me')->assertUnauthorized();
        $this->assertSame($user->id, AgentOAuthToken::firstOrFail()->user_id);
        $this->assertSame($station->id, AgentOAuthToken::latest('id')->firstOrFail()->station_id);
    }

    public function test_master_logout_revokes_the_current_station_session(): void
    {
        [$tokens, $user] = $this->authorizeAgent();
        $oauth = AgentOAuthToken::where('access_token_hash', hash('sha256', $tokens['access_token']))->firstOrFail();

        $this->assertNull($oauth->revoked_at);
        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertNotNull($oauth->fresh()->revoked_at);
    }

    public function test_master_logout_revokes_oauth_and_closes_the_active_agent_work_session(): void
    {
        [$tokens, $user] = $this->authorizeAgent();
        $station = AgentStation::firstOrFail();
        $session = $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token'], 'Idempotency-Key' => 'logout-session-start'])
            ->postJson('https://localhost/api/agent/sessions/start', [
                'installationId' => $station->installation_id, 'stationId' => $station->id, 'studioId' => $station->studio_id, 'userId' => $user->id,
            ])->assertCreated()->json();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertDatabaseHas('agent_work_sessions', [
            'id' => $session['sessionId'], 'status' => 'ended', 'end_reason' => 'logout',
        ]);
        $this->assertNotNull(AgentOAuthToken::where('station_id', $station->id)->latest('id')->firstOrFail()->revoked_at);
    }

    public function test_work_session_is_unique_and_heartbeat_records_obs_metrics(): void
    {
        [$tokens, $user] = $this->authorizeAgent();
        $station = AgentStation::firstOrFail();
        $headers = ['Authorization' => 'Bearer '.$tokens['access_token'], 'Idempotency-Key' => 'session-start-1'];
        $payload = ['installationId' => $station->installation_id, 'stationId' => $station->id, 'studioId' => $station->studio_id, 'userId' => $user->id];

        $startResponse = $this->withHeaders($headers)->postJson('https://localhost/api/agent/sessions/start', $payload)->assertCreated();
        $start = $startResponse->json();
        $this->withHeaders($headers)->postJson('https://localhost/api/agent/sessions/start', $payload)->assertCreated()->assertJsonPath('sessionId', $start['sessionId']);

        Carbon::setTestNow(now()->addSeconds(30));
        $heartbeat = $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token'], 'Idempotency-Key' => 'heartbeat-session-1'])
            ->postJson('https://localhost/api/agent/heartbeat', [
                'id' => (string) Str::uuid(), 'sentAt' => now()->toIso8601String(), 'active' => true,
                'status' => [...$this->heartbeatStatus($station, true), 'sessionId' => $start['sessionId'], 'sessionStartedAt' => $start['startedAt'], 'configurationAvailable' => false],
            ]);
        $heartbeat->assertNoContent();
        $this->assertDatabaseHas('agent_work_sessions', ['id' => $start['sessionId'], 'total_connected_seconds' => 30, 'total_streaming_seconds' => 0]);
        Carbon::setTestNow();
    }

    public function test_first_authenticated_heartbeat_registers_legacy_agent_work_session(): void
    {
        [$tokens, $user] = $this->authorizeAgent();
        $station = AgentStation::firstOrFail();
        $sessionId = (string) Str::uuid();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tokens['access_token'],
            'Idempotency-Key' => 'legacy-heartbeat-session-1',
        ])->postJson('https://localhost/api/agent/heartbeat', [
            'id' => (string) Str::uuid(),
            'sentAt' => now()->toIso8601String(),
            'active' => true,
            'status' => [
                ...$this->heartbeatStatus($station, false),
                'sessionId' => $sessionId,
                'sessionStartedAt' => now()->subMinute()->toIso8601String(),
                'userId' => (string) ($user->id + 999),
                'userRole' => 'model',
                'configurationAvailable' => false,
            ],
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('agent_work_sessions', [
            'id' => $sessionId,
            'user_id' => $user->id,
            'station_id' => $station->id,
            'status' => 'active',
        ]);
    }

    public function test_transmission_start_and_stop_are_recorded_with_server_time(): void
    {
        [$tokens, $user] = $this->authorizeAgent();
        $station = AgentStation::firstOrFail();
        $headers = ['Authorization' => 'Bearer '.$tokens['access_token'], 'Idempotency-Key' => 'transmission-session-start'];
        $start = $this->withHeaders($headers)->postJson('https://localhost/api/agent/sessions/start', [
            'installationId' => $station->installation_id, 'stationId' => $station->id, 'studioId' => $station->studio_id, 'userId' => $user->id,
        ])->assertCreated()->json();

        $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token'], 'Idempotency-Key' => 'transmission-start-1'])
            ->postJson('https://localhost/api/agent/sessions/'.$start['sessionId'].'/transmission/start')->assertOk()->assertJsonPath('status', 'active');
        Carbon::setTestNow(now()->addSeconds(20));
        $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token'], 'Idempotency-Key' => 'transmission-stop-1'])
            ->postJson('https://localhost/api/agent/sessions/'.$start['sessionId'].'/transmission/stop')->assertOk();

        $this->assertDatabaseHas('agent_work_sessions', ['id' => $start['sessionId'], 'total_streaming_seconds' => 20, 'last_streaming' => 0]);
        Carbon::setTestNow();
    }

    public function test_work_session_ends_on_explicit_end_and_oauth_revocation(): void
    {
        [$tokens] = $this->authorizeAgent();
        $station = AgentStation::firstOrFail();
        $start = $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token'], 'Idempotency-Key' => 'session-start-end'])
            ->postJson('https://localhost/api/agent/sessions/start', ['installationId' => $station->installation_id, 'stationId' => $station->id, 'studioId' => $station->studio_id, 'userId' => AgentOAuthToken::firstOrFail()->user_id])->assertCreated()->json();

        $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token'], 'Idempotency-Key' => 'session-end-1'])
            ->postJson('https://localhost/api/agent/sessions/'.$start['sessionId'].'/end', ['reason' => 'logout'])->assertOk()->assertJsonPath('status', 'ended');
        $this->assertDatabaseHas('agent_work_sessions', ['id' => $start['sessionId'], 'status' => 'ended', 'end_reason' => 'logout']);

        $second = $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token'], 'Idempotency-Key' => 'session-start-revoke'])
            ->postJson('https://localhost/api/agent/sessions/start', ['installationId' => $station->installation_id, 'stationId' => $station->id, 'studioId' => $station->studio_id, 'userId' => AgentOAuthToken::firstOrFail()->user_id])->assertCreated()->json();
        $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token']])->postJson('https://localhost/api/agent/oauth/revoke')->assertNoContent();
        $this->assertDatabaseHas('agent_work_sessions', ['id' => $second['sessionId'], 'status' => 'revoked', 'end_reason' => 'revoked']);
    }

    public function test_stale_work_session_is_expired_but_reconnection_before_timeout_keeps_it_active(): void
    {
        [$tokens, $user] = $this->authorizeAgent();
        $station = AgentStation::firstOrFail();
        $start = $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token'], 'Idempotency-Key' => 'session-start-timeout'])
            ->postJson('https://localhost/api/agent/sessions/start', ['installationId' => $station->installation_id, 'stationId' => $station->id, 'studioId' => $station->studio_id, 'userId' => $user->id])->assertCreated()->json();
        $session = \App\Models\Agent\AgentWorkSession::findOrFail($start['sessionId']);
        $session->update(['last_heartbeat_at' => now()->subSeconds(60)]);
        $this->artisan('agent:maintain')->assertSuccessful();
        $this->assertSame('active', $session->fresh()->status);
        $session->update(['last_heartbeat_at' => now()->subSeconds(91)]);
        $this->artisan('agent:maintain')->assertSuccessful();
        $this->assertSame('expired', $session->fresh()->status);
    }

    private function authorizeAgent(): array
    {
        $user = User::factory()->create()->assignRole('admin');
        return $this->authorizeAgentFor($user, (string) Str::uuid());
    }

    private function authorizeAgentFor(User $user, string $installation): array
    {
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $redirect = 'http://127.0.0.1:43128/oauth/callback';
        $response = $this->actingAs($user)->get('/oauth/agent/authorize?'.http_build_query([
            'client_id' => 'velvet-agent', 'redirect_uri' => $redirect, 'response_type' => 'code',
            'installation_id' => $installation, 'hostname' => 'station-test', 'studio_id' => 'studio-bogota',
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ]));
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'))['query'] ?? '', $query);
        $this->assertArrayHasKey('code', $query);

        return [$this->post('https://localhost/api/agent/oauth/token', [
            'grant_type' => 'authorization_code', 'client_id' => 'velvet-agent', 'code' => $query['code'],
            'redirect_uri' => $redirect, 'code_verifier' => $verifier,
        ], ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'])->assertOk()->json(), $user];
    }

    private function heartbeatStatus(AgentStation $station, bool $streaming): array
    {
        return [
            'identity' => ['installationId' => $station->installation_id, 'hostname' => $station->hostname],
            'enrollment' => 2, 'masterConnected' => true, 'lastSync' => null, 'version' => '1.0.0', 'uptimeSeconds' => 30,
            'mode' => 'master-api',
            'metrics' => ['hostname' => $station->hostname, 'operatingSystem' => 'macOS', 'architecture' => 'arm64', 'cpuCount' => 8, 'cpuPercent' => 20, 'ramTotalBytes' => 100, 'ramAvailableBytes' => 50, 'ramUsedPercent' => 50, 'diskAvailableBytes' => 1000],
            'obs' => ['processRunning' => true, 'webSocketConnected' => true, 'streaming' => $streaming, 'currentScene' => 'Escena 1', 'scenes' => ['Escena 1'], 'error' => null],
        ];
    }
}
