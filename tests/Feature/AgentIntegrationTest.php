<?php

namespace Tests\Feature;

use App\Models\Agent\AgentCommand;
use App\Models\Agent\AgentEnrollmentToken;
use App\Models\Agent\AgentIdempotencyKey;
use App\Models\Agent\AgentStation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VelvetMasterOperationalPermissionsSeeder;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.studios' => ['studio-a', 'studio-b'], 'agent.master_url' => 'https://master.test']);
    }

    private function token(string $studio = 'studio-a'): string
    {
        $token = Str::random(64);
        AgentEnrollmentToken::create(['token_hash' => hash('sha256', $token), 'studio_id' => $studio, 'issued_by' => User::factory()->create()->id, 'expires_at' => now()->addMinutes(10)]);

        return $token;
    }

    private function enroll(?string $installation = null, ?string $token = null, ?string $key = null)
    {
        return $this->postJson('https://localhost/api/agent/enroll', ['installationId' => $installation ?? (string) Str::uuid(), 'hostname' => 'station-01', 'token' => $token ?? $this->token()], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function station(): array
    {
        $id = (string) Str::uuid();
        $token = $this->enroll($id)->assertOk()->json('deviceToken');

        return [AgentStation::where('installation_id', $id)->firstOrFail(), $token];
    }

    private function send(string $path, array $data, string $token, ?string $key = null)
    {
        return $this->postJson('https://localhost/api/agent/'.$path, $data, ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function heartbeat(AgentStation $station): array
    {
        return ['id' => (string) Str::uuid(), 'sentAt' => now()->toIso8601String(), 'status' => [
            'identity' => ['installationId' => $station->installation_id, 'hostname' => $station->hostname],
            'enrollment' => 1, 'masterConnected' => true, 'lastSync' => null, 'version' => '0.1.0', 'uptimeSeconds' => 42.5,
            'metrics' => ['hostname' => $station->hostname, 'operatingSystem' => 'Windows', 'architecture' => 'x64', 'cpuCount' => 8, 'cpuPercent' => null, 'ramTotalBytes' => null, 'ramAvailableBytes' => null, 'ramUsedPercent' => null, 'diskAvailableBytes' => null],
            'obs' => ['processRunning' => false, 'webSocketConnected' => false, 'streaming' => null, 'currentScene' => null, 'scenes' => null, 'error' => null], 'mode' => 'master-api',
        ]];
    }

    private function command(AgentStation $station, array $overrides = []): AgentCommand
    {
        return AgentCommand::create([...['id' => (string) Str::uuid(), 'station_id' => $station->id, 'operation' => 'RefreshStatus', 'issued_by' => User::factory()->create()->id, 'expires_at' => now()->addMinute()], ...$overrides]);
    }

    public function test_enrollment_retries_return_same_encrypted_credential_without_consuming_another_token(): void
    {
        $id = (string) Str::uuid();
        $token = $this->token();
        $key = (string) Str::uuid();
        $credential = $this->enroll($id, $token, $key)->assertOk()->json('deviceToken');
        $this->enroll($id, $token, $key)->assertOk()->assertJson(['deviceToken' => $credential]);
        $this->enroll($id, $token)->assertUnauthorized();
        $second = $this->token();
        $this->enroll($id, $second, $key)->assertConflict();
        $this->assertNull(AgentEnrollmentToken::where('token_hash', hash('sha256', $second))->first()->used_at);
        $this->assertDatabaseCount('agent_stations', 1);
        $this->assertNotSame($credential, AgentStation::first()->credential_hash);
        $this->assertStringNotContainsString($credential, AgentIdempotencyKey::first()->getRawOriginal('response'));
        $this->assertStringNotContainsString('credential_hash', AgentStation::first()->toJson());
    }

    public function test_expired_invalid_and_consumed_enrollment_tokens_fail(): void
    {
        $token = $this->token();
        AgentEnrollmentToken::query()->update(['expires_at' => now()->subSecond()]);
        $this->enroll(token: $token)->assertUnauthorized();
        $this->enroll(token: 'unknown')->assertUnauthorized();
        $this->assertDatabaseCount('agent_stations', 0);
    }

    public function test_expired_enrollment_replay_does_not_reissue_credentials(): void
    {
        $id = (string) Str::uuid();
        $token = $this->token();
        $key = (string) Str::uuid();
        $this->enroll($id, $token, $key)->assertOk();
        $this->travel(6)->minutes();
        $this->enroll($id, $token, $key)->assertConflict();
    }

    public function test_reenrollment_rotates_only_its_station_and_old_replay_cannot_restore_credential(): void
    {
        $id = (string) Str::uuid();
        $token = $this->token();
        $key = (string) Str::uuid();
        $old = $this->enroll($id, $token, $key)->assertOk()->json('deviceToken');
        [$other, $otherToken] = $this->station();
        $this->enroll($id, $this->token('studio-b'))->assertConflict();
        $new = $this->enroll($id)->assertOk()->json('deviceToken');
        $this->assertNotSame($new, $old);
        $this->enroll($id, $token, $key)->assertUnauthorized();
        $this->getJson('https://localhost/api/agent/configuration', ['Authorization' => 'Bearer '.$old])->assertUnauthorized();
        $this->getJson('https://localhost/api/agent/configuration', ['Authorization' => 'Bearer '.$new])->assertOk();
        $this->getJson('https://localhost/api/agent/configuration', ['Authorization' => 'Bearer '.$otherToken])->assertOk();
    }

    public function test_heartbeat_accepts_null_metrics_and_uuid_deduplication_survives_different_keys(): void
    {
        [$station, $token] = $this->station();
        $data = $this->heartbeat($station);
        $key = (string) Str::uuid();
        $this->send('heartbeat', $data, $token, $key)->assertNoContent();
        $seen = $station->fresh()->last_seen_at;
        $this->travel(1)->minutes();
        $this->send('heartbeat', $data, $token, $key)->assertNoContent();
        $this->send('heartbeat', $data, $token)->assertNoContent();
        $this->assertTrue($seen->equalTo($station->fresh()->last_seen_at));
        $this->assertDatabaseCount('agent_heartbeats', 1);
        $this->assertSame('0.1.0', $station->fresh()->version);
        $this->assertFalse($station->fresh()->obs_status['processRunning']);
        $this->assertSame('Windows', $station->fresh()->system_metrics['operatingSystem']);
        $this->assertNotNull($station->fresh()->obs_reported_at);
        $data['status']['uptimeSeconds']++;
        $this->send('heartbeat', $data, $token, $key)->assertConflict();
        $this->send('heartbeat', $data, $token)->assertConflict();
    }

    public function test_heartbeat_accepts_oauth_user_session_fields(): void
    {
        [$station, $token] = $this->station();
        $data = $this->heartbeat($station);
        $data['status']['userAuthenticated'] = true;
        $data['status']['userId'] = (string) User::factory()->create()->id;
        $data['status']['userDisplayName'] = 'Modelo de prueba';
        $data['status']['userRole'] = 'model';
        $data['status']['configurationAvailable'] = false;

        $this->send('heartbeat', $data, $token)->assertNoContent();
    }

    public function test_station_identity_and_payload_uuid_cannot_be_used_by_other_station(): void
    {
        [$a, $tokenA] = $this->station();
        [$b, $tokenB] = $this->station();
        $data = $this->heartbeat($a);
        $this->send('heartbeat', $data, $tokenB)->assertForbidden();
        $this->send('heartbeat', $data, $tokenA)->assertNoContent();
        $data['status']['identity']['installationId'] = $b->installation_id;
        $this->send('heartbeat', $data, $tokenB)->assertConflict();
        $command = $this->command($a);
        $this->send('commands/'.$command->id.'/result', ['commandId' => $command->id, 'success' => true, 'message' => 'ok'], $tokenB)->assertNotFound();
    }

    public function test_invalid_metrics_missing_fields_and_future_timestamps_are_rejected(): void
    {
        [$station, $token] = $this->station();
        $data = $this->heartbeat($station);
        $data['status']['metrics']['cpuPercent'] = 101;
        $this->send('heartbeat', $data, $token)->assertBadRequest();
        $data = $this->heartbeat($station);
        unset($data['status']['metrics']['cpuPercent']);
        $this->send('heartbeat', $data, $token)->assertBadRequest();
        $data = $this->heartbeat($station);
        $data['sentAt'] = now()->addHour()->toIso8601String();
        $this->send('heartbeat', $data, $token)->assertBadRequest();
        $this->assertDatabaseCount('agent_heartbeats', 0);
    }

    public function test_events_are_sanitized_and_deduplicated(): void
    {
        [$station, $token] = $this->station();
        $data = ['id' => (string) Str::uuid(), 'at' => now()->toIso8601String(), 'kind' => 'obs.error', 'message' => '<b>Failure</b> password=hidden Bearer secret-token'];
        $this->send('events', $data, $token)->assertNoContent();
        $this->send('events', $data, $token)->assertNoContent();
        $this->assertDatabaseCount('agent_events', 1);
        $payload = DB::table('agent_events')->first()->payload;
        $this->assertStringNotContainsString('hidden', $payload);
        $this->assertStringNotContainsString('secret-token', $payload);
        $this->assertStringNotContainsString('<b>', $payload);
        $data['id'] = (string) Str::uuid();
        $data['message'] = '';
        $this->send('events', $data, $token)->assertNoContent();
    }

    public function test_terminal_result_is_immutable_even_with_new_idempotency_key(): void
    {
        [$station, $token] = $this->station();
        $command = $this->command($station);
        $data = ['commandId' => $command->id, 'success' => true, 'message' => 'done'];
        $this->send('commands/'.$command->id.'/result', $data, $token)->assertNoContent();
        $this->send('commands/'.$command->id.'/result', $data, $token)->assertNoContent();
        $data['success'] = false;
        $this->send('commands/'.$command->id.'/result', $data, $token)->assertConflict();
        $this->assertSame('completed', $command->fresh()->state);
        $data['commandId'] = (string) Str::uuid();
        $this->send('commands/'.$command->id.'/result', $data, $token)->assertBadRequest();
    }

    public function test_unknown_expired_and_disallowed_commands_do_not_accept_results(): void
    {
        [$station, $token] = $this->station();
        foreach ([['expires_at' => now()->subSecond()], ['operation' => 'Shell'], ['state' => 'cancelled']] as $overrides) {
            $command = $this->command($station, $overrides);
            $this->send('commands/'.$command->id.'/result', ['commandId' => $command->id, 'success' => true, 'message' => 'ok'], $token)->assertConflict();
            $this->assertNull($command->fresh()->result);
        }
        $id = (string) Str::uuid();
        $this->send('commands/'.$id.'/result', ['commandId' => $id, 'success' => true, 'message' => 'ok'], $token)->assertNotFound();
    }

    public function test_device_authentication_abilities_and_revocation_are_checked_before_replay(): void
    {
        [$station, $token] = $this->station();
        $data = $this->heartbeat($station);
        $key = (string) Str::uuid();
        $this->getJson('https://localhost/api/agent/configuration')->assertUnauthorized();
        $this->send('heartbeat', $data, 'invalid')->assertUnauthorized();
        $station->update(['abilities' => ['agent:heartbeat']]);
        $this->getJson('https://localhost/api/agent/configuration', ['Authorization' => 'Bearer '.$token])->assertForbidden();
        $this->send('heartbeat', $data, $token, $key)->assertNoContent();
        $station->update(['revoked_at' => now()]);
        $this->send('heartbeat', $data, $token, $key)->assertUnauthorized();
    }

    public function test_transport_payload_size_idempotency_header_and_retry_after(): void
    {
        $this->postJson('/api/agent/enroll', [])->assertBadRequest();
        $this->enroll(token: str_repeat('a', 70000))->assertStatus(413);
        [$station, $token] = $this->station();
        $this->postJson('https://localhost/api/agent/heartbeat', $this->heartbeat($station), ['Authorization' => 'Bearer '.$token])->assertBadRequest();
        // Station-specific limiter can recover once the window has elapsed.
        for ($i = 0; $i < 120; $i++) {
            $response = $this->getJson('https://localhost/api/agent/configuration', ['Authorization' => 'Bearer '.$token]);
        }
        $response->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $this->getJson('https://localhost/api/agent/configuration', ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    public function test_administration_uses_existing_permissions_and_controls_configuration_and_revocation(): void
    {
        $this->withHeader('Idempotency-Key', (string) Str::uuid());
        $this->seed([RolePermissionSeeder::class, VelvetMasterOperationalPermissionsSeeder::class]);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('https://localhost/admin/agent/enrollment-tokens', ['studioId' => 'studio-a'])->assertForbidden();
        $user->assignRole('admin');
        $this->actingAs($user)->postJson('https://localhost/admin/agent/enrollment-tokens', ['studioId' => 'arbitrary'])->assertBadRequest();
        $temporary = $this->actingAs($user)->postJson('https://localhost/admin/agent/enrollment-tokens', ['studioId' => 'studio-a'])->assertCreated()->json('token');
        $id = (string) Str::uuid();
        $device = $this->enroll($id, $temporary)->assertOk()->json('deviceToken');
        $station = AgentStation::where('installation_id', $id)->first();
        $this->putJson('https://localhost/admin/agent/stations/'.$station->id.'/configuration', ['heartbeatSeconds' => 60])->assertNoContent();
        $this->getJson('https://localhost/api/agent/configuration', ['Authorization' => 'Bearer '.$device])->assertOk()->assertExactJson(['masterUrl' => 'https://master.test', 'studioId' => 'studio-a', 'heartbeatSeconds' => 60, 'development' => false])->assertHeader('X-Agent-Configuration-Version', '2');
        $path = 'https://localhost/admin/agent/stations/'.$station->id.'/commands';
        $this->postJson($path, ['operation' => 'Shell', 'expiresAt' => now()->addMinute()->toIso8601String()])->assertBadRequest();
        $this->postJson($path, ['operation' => 'RefreshStatus', 'expiresAt' => now()->subMinute()->toIso8601String()])->assertBadRequest();
        $this->postJson($path, ['operation' => 'RefreshStatus', 'expiresAt' => now()->addMinute()->toIso8601String()])->assertCreated();
        $this->postJson('https://localhost/admin/agent/stations/'.$station->id.'/revoke')->assertNoContent();
        $this->assertNotNull($station->fresh()->revoked_at);
        $this->assertSame('cancelled', AgentCommand::first()->state);
        $this->getJson('https://localhost/api/agent/configuration', ['Authorization' => 'Bearer '.$device])->assertUnauthorized();
    }

    public function test_dotnet_timestamp_precision_and_delayed_offline_queue_are_supported(): void
    {
        [$station, $token] = $this->station();
        $data = $this->heartbeat($station);
        $data['sentAt'] = now()->subDays(2)->format('Y-m-d\\TH:i:s').'.1234567Z';
        $data['status']['lastSync'] = now()->format('Y-m-d\\TH:i:s').'.1+00:00';
        $this->send('heartbeat', $data, $token)->assertNoContent();
        $data['id'] = (string) Str::uuid();
        $data['sentAt'] = '2026-02-30T12:00:00Z';
        $this->send('heartbeat', $data, $token)->assertBadRequest();
    }

    public function test_temporary_server_failure_can_retry_without_consuming_enrollment_token(): void
    {
        $id = (string) Str::uuid();
        $temporary = $this->token();
        $key = (string) Str::uuid();
        config(['agent.master_url' => 'http://not-secure.test']);
        $this->enroll($id, $temporary, $key)->assertStatus(503);
        $this->assertDatabaseCount('agent_idempotency_keys', 0);
        $this->assertNull(AgentEnrollmentToken::first()->used_at);
        config(['agent.master_url' => 'https://master.test']);
        $this->enroll($id, $temporary, $key)->assertOk();
    }

    public function test_maintenance_expires_commands_erases_replay_secrets_and_recovers_online(): void
    {
        [$station, $token] = $this->station();
        $this->send('heartbeat', $this->heartbeat($station), $token)->assertNoContent();
        $command = $this->command($station);
        $this->travel(6)->minutes();
        $this->artisan('agent:maintain')->assertSuccessful();
        $this->assertNotNull($station->fresh()->offline_at);
        $this->assertSame('expired', $command->fresh()->state);
        $this->assertNull(AgentIdempotencyKey::where('scope', 'enroll')->first()->response);
        $this->send('heartbeat', $this->heartbeat($station), $token)->assertNoContent();
        $this->assertNull($station->fresh()->offline_at);
    }

    public function test_administrative_tokens_and_commands_are_idempotent(): void
    {
        $this->seed([RolePermissionSeeder::class, VelvetMasterOperationalPermissionsSeeder::class]);
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->withHeader('Idempotency-Key', (string) Str::uuid());
        $path = 'https://localhost/admin/agent/enrollment-tokens';
        $token = $this->postJson($path, ['studioId' => 'studio-a'])->assertCreated()->json('token');
        $this->postJson($path, ['studioId' => 'studio-a'])->assertCreated()->assertJson(['token' => $token]);
        $this->postJson($path, ['studioId' => 'studio-b'])->assertConflict();
        $this->assertDatabaseCount('agent_enrollment_tokens', 1);
        $id = (string) Str::uuid();
        $this->enroll($id, $token)->assertOk();
        $station = AgentStation::where('installation_id', $id)->first();
        $path = 'https://localhost/admin/agent/stations/'.$station->id.'/commands';
        $data = ['operation' => 'RefreshStatus', 'expiresAt' => now()->addMinute()->toIso8601String()];
        $command = $this->postJson($path, $data)->assertCreated()->json('id');
        $this->travel(2)->minutes();
        $this->postJson($path, $data)->assertCreated()->assertJson(['id' => $command]);
        $this->assertDatabaseCount('agent_commands', 1);
    }

    public function test_json_types_and_object_property_order_follow_the_contract(): void
    {
        [$station, $token] = $this->station();
        $data = $this->heartbeat($station);
        $key = (string) Str::uuid();
        $this->send('heartbeat', $data, $token, $key)->assertNoContent();
        $this->send('heartbeat', array_reverse($data, true), $token, $key)->assertNoContent();
        $data['id'] = (string) Str::uuid();
        $data['status']['metrics']['cpuCount'] = '8';
        $this->send('heartbeat', $data, $token)->assertBadRequest();
        $data['status']['metrics']['cpuCount'] = 8;
        $data['status']['masterConnected'] = 1;
        $this->send('heartbeat', $data, $token)->assertBadRequest();
    }

    public function test_non_admin_cannot_configure_revoke_or_reserve_commands(): void
    {
        [$station, $token] = $this->station();
        $user = User::factory()->create();
        $this->actingAs($user)->withHeader('Idempotency-Key', (string) Str::uuid());
        $path = 'https://localhost/admin/agent/stations/'.$station->id;
        $this->putJson($path.'/configuration', ['heartbeatSeconds' => 60])->assertForbidden();
        $this->postJson($path.'/revoke')->assertForbidden();
        $this->postJson($path.'/commands', ['operation' => 'RefreshStatus', 'expiresAt' => now()->addMinute()->toIso8601String()])->assertForbidden();
        $this->assertNull($station->fresh()->revoked_at);
        $this->assertDatabaseCount('agent_commands', 0);
    }

    public function test_failed_requests_count_toward_station_limits_with_database_cache(): void
    {
        $this->app->instance(RateLimiter::class, new RateLimiter(app('cache')->store('database')));
        \Illuminate\Support\Facades\RateLimiter::clearResolvedInstance(RateLimiter::class);
        $limiter = app(RateLimiter::class);
        $limiter->for('agent-ip', fn ($request) => Limit::perMinute(240)->by($request->ip()));
        $limiter->for('agent-enroll', fn ($request) => Limit::perMinute(10)->by($request->ip()));
        $limiter->for('agent-station', fn ($request) => Limit::perMinute(2)->by('station:'.$request->attributes->get('agentStation')->id));
        [$station, $token] = $this->station();
        $this->send('heartbeat', [], $token)->assertBadRequest();
        $this->send('heartbeat', [], $token)->assertBadRequest();
        $this->send('heartbeat', [], $token)->assertStatus(429)->assertHeader('Retry-After');
    }
}
