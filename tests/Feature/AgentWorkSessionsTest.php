<?php

namespace Tests\Feature;

use App\Models\Agent\AgentStation;
use App\Models\Agent\AgentWorkSession;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgentWorkSessionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_model_can_only_view_own_work_sessions(): void
    {
        $model = User::factory()->create(['is_active' => true])->assignRole('model');
        $other = User::factory()->create(['is_active' => true])->assignRole('model');
        $this->createWorkSession($model);
        $this->createWorkSession($other);

        $this->actingAs($model)->get('/model/work-sessions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Agent/WorkSessions/Index')->where('scope', 'own')->has('sessions', 1));
    }

    public function test_admin_can_view_all_and_monitor_only_assigned_models(): void
    {
        $admin = User::factory()->create(['is_active' => true])->assignRole('admin');
        $monitor = User::factory()->create(['is_active' => true])->assignRole('monitor');
        $assignedModel = User::factory()->create(['is_active' => true])->assignRole('model');
        $unassignedModel = User::factory()->create(['is_active' => true])->assignRole('model');
        $monitorCandidate = $this->candidate($monitor, 'MONITOR');
        $assignedCandidate = $this->candidate($assignedModel, 'MODEL');
        $this->candidate($unassignedModel, 'MODEL');
        DB::table('model_monitor_assignments')->insert([
            'model_candidate_id' => $assignedCandidate,
            'monitor_candidate_id' => $monitorCandidate,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createWorkSession($assignedModel);
        $this->createWorkSession($unassignedModel);

        $this->actingAs($admin)->get('/admin/work-sessions')->assertOk()->assertInertia(fn ($page) => $page->where('scope', 'all')->has('sessions', 2));
        $this->actingAs($monitor)->get('/monitor/work-sessions')->assertOk()->assertInertia(fn ($page) => $page->where('scope', 'assigned')->has('sessions', 1)->where('sessions.0.user.id', $assignedModel->id));
    }

    private function createWorkSession(User $user): AgentWorkSession
    {
        $station = AgentStation::create([
            'installation_id' => (string) Str::uuid(),
            'hostname' => 'station-'.$user->id,
            'studio_id' => 'studio-test',
            'credential_hash' => hash('sha256', Str::random(32)),
            'abilities' => ['agent:heartbeat'],
            'configuration' => ['heartbeatSeconds' => 30],
        ]);

        return AgentWorkSession::create([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'station_id' => $station->id,
            'installation_id' => $station->installation_id, 'studio_id' => $station->studio_id,
            'started_at' => now()->subMinutes(20), 'last_heartbeat_at' => now()->subMinutes(5),
            'status' => 'ended', 'ended_at' => now(), 'end_reason' => 'logout',
            'total_connected_seconds' => 900, 'total_streaming_seconds' => 600,
        ]);
    }

    private function candidate(User $user, string $type): int
    {
        $lead = DB::table('leads')->insertGetId([
            'code' => 'LEAD-'.$user->id.'-'.Str::random(5), 'candidate_type' => $type,
            'first_name' => $user->name, 'last_name' => 'Test', 'phone' => '3000000000',
            'email' => $user->email, 'city' => 'Bogotá', 'birth_date' => '1990-01-01',
            'availability' => 'FULL', 'work_mode' => 'REMOTE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('candidates')->insertGetId([
            'code' => 'CAND-'.$user->id.'-'.Str::random(5), 'lead_id' => $lead, 'user_id' => $user->id,
            'candidate_type' => $type, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
