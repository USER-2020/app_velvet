<?php

namespace Tests\Feature;

use App\Models\LauncherEnrollmentToken;
use App\Models\LauncherStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LauncherIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_launcher_can_enroll_read_station_and_send_bearer_heartbeat(): void
    {
        $code = Str::random(48);
        LauncherEnrollmentToken::create([
            'token_hash' => hash('sha256', $code),
            'studio_id' => 'studio-paris',
            'issued_by' => User::factory()->create()->id,
            'expires_at' => now()->addMinutes(10),
        ]);

        $installationId = (string) Str::uuid();
        $registration = $this->postJson('https://localhost/api/launcher/enrollment', [
            'code' => $code,
            'computerName' => 'PC-SALA-PARIS',
            'operatingSystem' => 'Windows 11',
            'launcherVersion' => '0.1.0',
            'installationId' => $installationId,
        ])->assertOk()->json();

        $this->assertSame($installationId, $registration['installationId']);
        $this->assertDatabaseHas('launcher_stations', ['installation_id' => $installationId, 'studio_id' => 'studio-paris']);

        $headers = ['Authorization' => 'Bearer '.$registration['deviceCredential']];
        $this->getJson('https://localhost/api/launcher/station', $headers)->assertOk()->assertJsonPath('installationId', $installationId);
        $this->postJson('https://localhost/api/launcher/heartbeat', [
            'installationId' => $installationId,
            'computerName' => 'PC-SALA-PARIS',
            'operatingSystem' => 'Windows 11',
            'launcherVersion' => '0.1.0',
            'sentAt' => now()->toIso8601String(),
            'status' => ['bridge' => ['connected' => false]],
        ], $headers)->assertNoContent();

        $this->assertNotNull(LauncherStation::firstOrFail()->fresh()->last_seen_at);
        $this->assertDatabaseCount('launcher_heartbeats', 1);
    }

    public function test_launcher_rejects_a_credential_sent_in_the_json_body(): void
    {
        $this->postJson('https://localhost/api/launcher/heartbeat', ['credential' => 'not-a-bearer-token'])
            ->assertUnauthorized();
    }
}
