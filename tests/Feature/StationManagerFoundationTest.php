<?php

namespace Tests\Feature;

use App\Models\LauncherStation;
use App\Models\Room;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VelvetMasterOperationalPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Tests\TestCase;

class StationManagerFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_station_manager_schema_links_a_station_to_a_room_and_keeps_assignment_history(): void
    {
        $this->assertTrue(Schema::hasTable('launcher_stations'));
        $this->assertTrue(Schema::hasTable('launcher_station_assignments'));
        $this->assertTrue(Schema::hasColumns('launcher_station_assignments', [
            'station_id',
            'room_id',
            'model_candidate_id',
            'monitor_candidate_id',
            'starts_at',
            'ends_at',
            'status',
        ]));

        $room = Room::create(['code' => 'PARIS', 'name' => 'Room Paris', 'is_active' => true]);
        $station = LauncherStation::create([
            'installation_id' => '550e8400-e29b-41d4-a716-446655440000',
            'hostname' => 'PC-SALA-PARIS',
            'studio_id' => 'studio-paris',
            'room_id' => $room->id,
            'credential_hash' => hash('sha256', 'test-device-token'),
            'configuration' => ['heartbeatSeconds' => 30],
        ]);

        $this->assertSame($room->id, $station->room->id);
        $this->assertTrue($room->launcherStations->contains($station));
    }

    public function test_admin_receives_the_station_assignment_permission(): void
    {
        $this->seed([RolePermissionSeeder::class, VelvetMasterOperationalPermissionsSeeder::class]);

        $this->assertTrue(\App\Models\Role::where('slug', 'admin')->firstOrFail()->hasPermissionTo('stations.assign'));
    }

    public function test_admin_can_open_the_separate_station_manager_module(): void
    {
        $this->seed([RolePermissionSeeder::class, VelvetMasterOperationalPermissionsSeeder::class]);
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->get('/admin/station-manager')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/StationManager/Index'));
    }

    public function test_admin_can_generate_a_launcher_enrollment_code_and_receive_it_as_flash_data(): void
    {
        $this->seed([RolePermissionSeeder::class, VelvetMasterOperationalPermissionsSeeder::class]);
        config(['launcher.studios' => ['studio-manizales']]);
        $admin = User::factory()->create()->assignRole('admin');

        $response = $this->actingAs($admin)->post('/admin/station-manager/enrollment-codes', [
            'studio_id' => 'studio-manizales',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('launcherEnrollmentCode', function (array $payload): bool {
            return str_starts_with($payload['code'], 'vle_')
                && $payload['studioId'] === 'studio-manizales'
                && ! empty($payload['expiresAt']);
        });
        $this->assertDatabaseHas('launcher_enrollment_tokens', [
            'studio_id' => 'studio-manizales',
            'issued_by' => $admin->id,
        ]);
    }
}
