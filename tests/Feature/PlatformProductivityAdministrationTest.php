<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VelvetMasterOperationalPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformProductivityAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VelvetMasterOperationalPermissionsSeeder::class);
    }

    public function test_admin_uses_only_simulated_platform_connections_and_encrypted_credentials(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->post('/admin/platforms', ['code' => 'sandbox-cam', 'name' => 'Sandbox Cam'])->assertRedirect();
        $platform = \App\Models\PlatformCatalog::firstOrFail();

        $this->actingAs($admin)->post('/admin/platforms/accounts', [
            'platform_catalog_id' => $platform->id,
            'name' => 'Cuenta de pruebas',
            'connection_mode' => 'SIMULATED',
            'credentials' => ['token' => 'secret-not-plain'],
        ])->assertRedirect();

        $account = \App\Models\StudioAccount::firstOrFail();
        $this->assertNotSame('secret-not-plain', $account->getRawOriginal('encrypted_credentials'));
        $this->actingAs($admin)->post("/admin/platforms/accounts/{$account->id}/simulate-sync")->assertRedirect();
        $this->assertDatabaseHas('platform_sync_runs', ['studio_account_id' => $account->id, 'mode' => 'SIMULATED', 'status' => 'COMPLETED']);

        $this->actingAs($admin)->post("/admin/platforms/accounts/{$account->id}/import", ['original_name' => 'official.csv', 'content' => "date,tokens\n2026-10-12,1250"])->assertRedirect();
        $this->assertDatabaseHas('platform_import_batches', ['studio_account_id' => $account->id, 'rows_imported' => 1]);
    }

    public function test_sessions_do_not_duplicate_time_for_simultaneous_streams_and_metrics_are_auditable(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $model = $this->activeModel();
        $this->actingAs($admin)->post('/admin/productivity/sessions', ['candidate_id' => $model->id, 'started_at' => now()->subMinutes(30)->format('Y-m-d H:i:s'), 'source' => 'MANUAL'])->assertRedirect();
        $sessionId = (int) \DB::table('operational_sessions')->value('id');

        foreach (['Manual A', 'Manual B'] as $platform) {
            $this->actingAs($admin)->post("/admin/productivity/sessions/{$sessionId}/streams", ['platform_name' => $platform, 'started_at' => now()->subMinutes(20)->format('Y-m-d H:i:s'), 'data_origin' => 'MANUAL'])->assertRedirect();
        }
        $this->assertDatabaseCount('stream_sessions', 2);

        $this->actingAs($admin)->post('/admin/productivity/metrics', ['operational_session_id' => $sessionId, 'metric_key' => 'tokens', 'metric_value' => 100, 'unit' => 'TOKENS', 'data_origin' => 'MANUAL', 'recorded_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $metricId = (int) \DB::table('production_metrics')->value('id');
        $this->actingAs($admin)->patch("/admin/productivity/metrics/{$metricId}/validate")->assertRedirect();
        $this->assertDatabaseHas('production_metrics', ['id' => $metricId, 'origin' => 'MANUAL', 'is_validated' => true, 'validated_by' => $admin->id]);
    }

    public function test_administrator_can_register_internal_resources_without_external_finance_delivery(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->post('/admin/administration/branches', ['code' => 'BOG-01', 'name' => 'Bogota Central'])->assertRedirect();
        $branchId = (int) \DB::table('branches')->value('id');
        $this->actingAs($admin)->post('/admin/administration/assets', ['asset_tag' => 'CAM-001', 'name' => 'Camara', 'category' => 'EQUIPMENT', 'branch_id' => $branchId])->assertRedirect();
        $assetId = (int) \DB::table('inventory_assets')->value('id');
        $this->actingAs($admin)->post('/admin/administration/maintenance', ['inventory_asset_id' => $assetId, 'kind' => 'PREVENTIVE', 'description' => 'Revision mensual.'])->assertRedirect();
        $this->actingAs($admin)->get('/admin/administration')->assertOk();
        $this->actingAs($admin)->post('/admin/finance/exports')->assertRedirect();
        $this->assertDatabaseHas('finance_export_batches', ['status' => 'PENDING', 'created_by' => $admin->id]);
    }

    private function activeModel(): Candidate
    {
        $id = fake()->unique()->numerify('#####');
        $lead = Lead::create(['code' => "LEAD-{$id}", 'status' => 'CONVERTED', 'candidate_type' => 'MODEL', 'first_name' => 'Modelo', 'last_name' => $id, 'sex' => 'WOMAN', 'phone' => "300{$id}", 'email' => "model-{$id}@example.test", 'country' => 'Colombia', 'city' => 'Bogota', 'birth_date' => '1998-01-01', 'availability' => 'Tiempo completo', 'work_mode' => 'En estudio']);
        return Candidate::create(['code' => "CAN-{$id}", 'lead_id' => $lead->id, 'candidate_type' => 'MODEL', 'status' => 'ACTIVE']);
    }
}
