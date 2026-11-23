<?php

namespace Tests\Feature;

use App\Models\Agent\AgentStation;
use App\Models\Agent\AgentWorkSession;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\Lead;
use App\Models\OperationalRequest;
use App\Models\PlatformCatalog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModelPortalModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_model_can_open_the_new_portal_modules(): void
    {
        [$model, $candidate] = $this->model();

        $this->actingAs($model)->get('/model/time')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Model/Time')->has('summary')->has('sessions'));
        $this->actingAs($model)->get('/model/activity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Model/Activity')->where('candidate.code', $candidate->code));
        $this->actingAs($model)->get('/model/agenda')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Model/Agenda')->has('items'));
        $this->actingAs($model)->get('/model/requests')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Model/Requests')->where('canCreate', true));
        PlatformCatalog::create(['code' => 'chaturbate', 'name' => 'Chaturbate', 'adapter_key' => 'simulation', 'is_active' => true]);
        $this->actingAs($model)->get('/model/platform-requests')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Model/PlatformRequests')->has('platforms', 1));
    }

    public function test_model_time_is_scoped_to_the_authenticated_user(): void
    {
        [$model] = $this->model('one');
        [$other] = $this->model('two');
        $this->workSession($model);
        $this->workSession($other);

        $this->actingAs($model)->get('/model/time')->assertInertia(fn ($page) => $page->has('sessions', 1));
    }

    public function test_model_can_create_and_review_an_operational_request(): void
    {
        [$model, $candidate] = $this->model();

        $this->actingAs($model)->post('/portal/requests', [
            'type' => 'ROOM',
            'details' => 'Necesito confirmar la disponibilidad de una sala.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('operational_requests', [
            'candidate_id' => $candidate->id,
            'requested_by' => $model->id,
            'type' => 'ROOM',
            'status' => 'PENDING',
        ]);

        $this->actingAs($model)->get('/model/requests')->assertInertia(fn ($page) => $page->has('requests', 2));
    }

    public function test_model_can_request_a_platform_nickname_without_creating_an_account(): void
    {
        [$model, $candidate] = $this->model();
        $platform = PlatformCatalog::create(['code' => 'chaturbate', 'name' => 'Chaturbate', 'adapter_key' => 'simulation', 'is_active' => true]);

        $this->actingAs($model)->post('/model/platform-requests', [
            'platform_catalog_id' => $platform->id,
            'nickname' => 'velvet_luna',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('operational_requests', [
            'candidate_id' => $candidate->id,
            'requested_by' => $model->id,
            'type' => 'PLATFORM_REQUEST',
            'status' => 'PENDING',
        ]);
        $request = OperationalRequest::query()->where('type', 'PLATFORM_REQUEST')->firstOrFail();
        $this->assertSame('velvet_luna', $request->metadata['nickname']);
        $this->assertDatabaseCount('model_platform_accounts', 0);

        $this->actingAs($model)->post('/model/platform-requests', [
            'platform_catalog_id' => $platform->id,
            'nickname' => 'velvet_luna_2',
        ])->assertSessionHasErrors('platform_catalog_id');
    }

    public function test_user_without_model_portal_access_cannot_open_the_new_modules(): void
    {
        $recruiter = User::factory()->create(['is_active' => true])->assignRole('recruiter');

        $this->actingAs($recruiter)->get('/model/time')->assertForbidden();
        $this->actingAs($recruiter)->get('/model/activity')->assertForbidden();
    }

    private function model(string $suffix = 'one'): array
    {
        $user = User::factory()->create(['is_active' => true])->assignRole('model');
        $lead = Lead::create([
            'code' => 'LEAD-MODEL-'.$suffix,
            'candidate_type' => 'MODEL',
            'first_name' => 'Modelo',
            'last_name' => ucfirst($suffix),
            'phone' => '30000000'.str_pad((string) strlen($suffix), 2, '0', STR_PAD_LEFT),
            'email' => 'model-'.$suffix.'@example.test',
            'city' => 'Bogotá',
            'birth_date' => '1998-01-01',
            'availability' => 'Tiempo completo',
            'work_mode' => 'En estudio',
            'status' => 'CONVERTED',
        ]);
        $candidate = Candidate::create([
            'code' => 'CAN-MODEL-'.$suffix,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'candidate_type' => 'MODEL',
            'status' => 'ACTIVE',
            'activated_at' => now(),
        ]);
        CandidateActivity::create(['candidate_id' => $candidate->id, 'user_id' => $user->id, 'type' => 'profile_checked', 'description' => 'Perfil operativo creado.']);
        OperationalRequest::create(['candidate_id' => $candidate->id, 'requested_by' => $user->id, 'type' => 'SUPPORT', 'status' => 'PENDING', 'details' => 'Solicitud de prueba.']);

        return [$user, $candidate];
    }

    private function workSession(User $user): AgentWorkSession
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
}
