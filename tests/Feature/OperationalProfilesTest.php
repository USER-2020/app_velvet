<?php

namespace Tests\Feature;

use App\Mail\CandidateAccessCredentialsMail;
use App\Models\Candidate;
use App\Models\Lead;
use App\Models\ModelMonitorAssignment;
use App\Models\ModelPlatformAccount;
use App\Models\OperationalTrainingRecord;
use App\Models\PlatformCatalog;
use App\Models\StudioAccount;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VelvetMasterOperationalPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OperationalProfilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(VelvetMasterOperationalPermissionsSeeder::class);
    }

    public function test_access_activation_creates_operational_profile_and_requires_induction_completion(): void
    {
        Mail::fake();
        $candidate = $this->candidate('MODEL', 'ONBOARDING');
        $recruiter = User::factory()->create()->assignRole('recruiter');

        $this->actingAs($recruiter)
            ->post("/admin/candidates/{$candidate->id}/activate-access")
            ->assertRedirect();

        Mail::assertSent(CandidateAccessCredentialsMail::class, function (CandidateAccessCredentialsMail $mail): bool {
            $content = $mail->content();

            return $content->view === 'emails.candidate-access-credentials'
                && filled($content->with['verificationUrl'] ?? null);
        });

        $candidate->refresh();
        $this->assertSame('INDUCTION', $candidate->status->value);
        $this->assertNotNull($candidate->user_id);
        $this->assertDatabaseHas('operational_profiles', [
            'candidate_id' => $candidate->id,
            'profile_type' => 'MODEL',
            'operational_status' => 'PENDING',
        ]);
        $this->assertDatabaseHas('operational_training_records', [
            'candidate_id' => $candidate->id,
            'kind' => 'ONBOARDING',
            'status' => 'COMPLETED',
        ]);

        $this->actingAs($recruiter)
            ->patch("/admin/candidates/{$candidate->id}/status", ['status' => 'READY_TO_ACTIVATE'])
            ->assertSessionHasErrors('status');

        $record = OperationalTrainingRecord::query()
            ->where('candidate_id', $candidate->id)
            ->where('kind', 'TRAINING')
            ->firstOrFail();
        $hr = User::factory()->create()->assignRole('hr');

        $this->actingAs($hr)
            ->patch("/admin/candidates/{$candidate->id}/training-records/{$record->id}", ['status' => 'COMPLETED'])
            ->assertRedirect();

        $this->actingAs($recruiter)
            ->patch("/admin/candidates/{$candidate->id}/status", ['status' => 'READY_TO_ACTIVATE'])
            ->assertRedirect();
        $this->actingAs($recruiter)
            ->patch("/admin/candidates/{$candidate->id}/status", ['status' => 'ACTIVE'])
            ->assertRedirect();

        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('operational_profiles', [
            'candidate_id' => $candidate->id,
            'operational_status' => 'ACTIVE',
        ]);
    }

    public function test_monitor_reassignment_closes_previous_assignment_and_keeps_history(): void
    {
        $manager = User::factory()->create()->assignRole('manager');
        $model = $this->candidate('MODEL', 'ACTIVE');
        $firstMonitor = $this->candidate('MONITOR', 'ACTIVE');
        $secondMonitor = $this->candidate('MONITOR', 'ACTIVE');

        $this->actingAs($manager)
            ->post("/admin/models/{$model->id}/monitor-assignments", [
                'monitor_candidate_id' => $firstMonitor->id,
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->post("/admin/models/{$model->id}/monitor-assignments", [
                'monitor_candidate_id' => $secondMonitor->id,
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($manager)
            ->post("/admin/models/{$model->id}/monitor-assignments", [
                'monitor_candidate_id' => $secondMonitor->id,
                'reason' => 'Cambio de cobertura del equipo.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('model_monitor_assignments', [
            'model_candidate_id' => $model->id,
            'monitor_candidate_id' => $firstMonitor->id,
            'reason' => 'Cambio de cobertura del equipo.',
        ]);
        $this->assertDatabaseHas('model_monitor_assignments', [
            'model_candidate_id' => $model->id,
            'monitor_candidate_id' => $secondMonitor->id,
            'ended_at' => null,
        ]);
        $this->assertSame(1, ModelMonitorAssignment::query()
            ->where('model_candidate_id', $model->id)
            ->whereNull('ended_at')
            ->count());
    }

    public function test_monitor_can_only_view_models_currently_assigned_to_them(): void
    {
        $monitorUser = User::factory()->create(['is_active' => true])->assignRole('monitor');
        $monitor = $this->candidate('MONITOR', 'ACTIVE', $monitorUser);
        $assignedModel = $this->candidate('MODEL', 'ACTIVE');
        $otherModel = $this->candidate('MODEL', 'ACTIVE');
        ModelMonitorAssignment::create([
            'model_candidate_id' => $assignedModel->id,
            'monitor_candidate_id' => $monitor->id,
            'assigned_at' => now(),
        ]);
        $this->assertSame($monitor->id, $monitorUser->candidate?->id);
        $this->assertSame($monitor->id, Candidate::query()->where('user_id', $monitorUser->id)->value('id'));
        $this->assertDatabaseHas('candidates', [
            'id' => $assignedModel->id,
            'candidate_type' => 'MODEL',
            'status' => 'ACTIVE',
        ]);
        $this->assertSame(2, Candidate::query()
            ->where('candidate_type', 'MODEL')
            ->whereIn('status', ['ADMITTED', 'WAITING', 'ONBOARDING', 'CONTRACTING', 'INDUCTION', 'READY_TO_ACTIVATE', 'ACTIVE'])
            ->count());
        $this->assertSame(1, Candidate::query()
            ->where('candidate_type', 'MODEL')
            ->whereIn('status', ['ADMITTED', 'WAITING', 'ONBOARDING', 'CONTRACTING', 'INDUCTION', 'READY_TO_ACTIVATE', 'ACTIVE'])
            ->whereHas('modelAssignments', fn ($assignment) => $assignment
                ->where('monitor_candidate_id', $monitor->id)
                ->whereNull('ended_at'))
            ->count());
        $this->actingAs($monitorUser)
            ->get('/monitor/models')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/MonitorModels/Index')
                ->has('models.data', 1)
                ->where('models.data.0.id', $assignedModel->id));

        $this->actingAs($monitorUser)
            ->get('/monitor/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Monitor/Overview')
                ->has('candidates', 1)
                ->where('candidates.0.id', $assignedModel->id)
                ->has('activityTrend', 6));

        $this->actingAs($monitorUser)
            ->get('/monitor/rooms')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Monitor/Dashboard')
                ->has('candidates', 1)
                ->where('candidates.0.id', $assignedModel->id));

        $this->actingAs($monitorUser)
            ->get("/monitor/models/{$assignedModel->id}")
            ->assertOk();
        $this->actingAs($monitorUser)
            ->get("/monitor/models/{$otherModel->id}")
            ->assertForbidden();
    }

    public function test_monitor_can_associate_and_unlink_platform_accounts_only_for_an_assigned_model(): void
    {
        $monitorUser = User::factory()->create(['is_active' => true])->assignRole('monitor');
        $monitor = $this->candidate('MONITOR', 'ACTIVE', $monitorUser);
        $assignedModel = $this->candidate('MODEL', 'ACTIVE');
        $otherModel = $this->candidate('MODEL', 'ACTIVE');
        ModelMonitorAssignment::create([
            'model_candidate_id' => $assignedModel->id,
            'monitor_candidate_id' => $monitor->id,
            'assigned_at' => now(),
        ]);
        $platform = PlatformCatalog::create(['code' => 'demo-platform', 'name' => 'Demo Platform', 'adapter_key' => 'simulation', 'is_active' => true]);
        $studioAccount = StudioAccount::create([
            'platform_catalog_id' => $platform->id,
            'name' => 'Cuenta Demo',
            'status' => 'MANUAL',
            'connection_mode' => 'MANUAL',
        ]);

        $this->actingAs($monitorUser)
            ->post("/monitor/models/{$assignedModel->id}/platform-accounts", [
                'studio_account_id' => $studioAccount->id,
                'account_identifier' => 'modelo_demo',
                'ownership_type' => 'STUDIO',
                'is_authorized' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('model_platform_accounts', [
            'candidate_id' => $assignedModel->id,
            'studio_account_id' => $studioAccount->id,
            'account_identifier' => 'modelo_demo',
            'is_authorized' => true,
        ]);

        $this->actingAs($monitorUser)
            ->post("/monitor/models/{$otherModel->id}/platform-accounts", [
                'studio_account_id' => $studioAccount->id,
                'account_identifier' => 'otra_modelo',
                'ownership_type' => 'STUDIO',
                'is_authorized' => true,
            ])
            ->assertForbidden();

        $link = ModelPlatformAccount::query()->where('candidate_id', $assignedModel->id)->firstOrFail();
        $this->actingAs($monitorUser)
            ->delete("/monitor/models/{$assignedModel->id}/platform-accounts/{$link->id}")
            ->assertRedirect();

        $this->assertNotNull($link->fresh()->unlinked_at);
    }

    public function test_model_profile_and_statistics_are_scoped_to_the_authenticated_model(): void
    {
        $modelUser = User::factory()->create(['is_active' => true])->assignRole('model');
        $model = $this->candidate('MODEL', 'ACTIVE', $modelUser);
        $otherModel = $this->candidate('MODEL', 'ACTIVE');

        $this->actingAs($modelUser)
            ->get('/model/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Model/Profile')
                ->where('profile.code', $model->code)
                ->where('profile.personal.email', $model->lead->email));

        $this->actingAs($modelUser)
            ->get('/model/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Model/Dashboard')
                ->where('candidate.id', $model->id)
                ->has('activityTrend', 6));

        $this->actingAs($modelUser)
            ->get('/model/rooms')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Model/Rooms')
                ->where('candidate.id', $model->id));

        $this->assertNotSame($otherModel->code, $model->code);
    }

    private function candidate(string $type, string $status, ?User $user = null): Candidate
    {
        $suffix = fake()->unique()->numerify('#####');
        $lead = Lead::create([
            'code' => "VEL-LEAD-{$suffix}",
            'status' => 'CONVERTED',
            'candidate_type' => $type,
            'first_name' => 'Persona',
            'last_name' => $suffix,
            'sex' => 'WOMAN',
            'phone' => "300{$suffix}",
            'email' => "persona-{$suffix}@example.test",
            'country' => 'Colombia',
            'city' => 'Bogota',
            'birth_date' => '1998-01-01',
            'availability' => 'Tiempo completo',
            'work_mode' => 'En estudio',
        ]);

        return Candidate::create([
            'code' => "VEL-CAN-{$suffix}",
            'lead_id' => $lead->id,
            'user_id' => $user?->id,
            'candidate_type' => $type,
            'status' => $status,
        ]);
    }
}
