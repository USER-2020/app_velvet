<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VelvetMasterOperationalPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VelvetMasterOperationalPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_permissions_seeder_is_idempotent_and_preserves_existing_assignments(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $manager = Role::query()->where('slug', 'manager')->firstOrFail();
        $custom = Permission::create(['name' => 'Permiso local', 'slug' => 'local.custom_permission', 'group' => 'Local']);
        $manager->permissions()->attach($custom->id);

        $this->seed(VelvetMasterOperationalPermissionsSeeder::class);
        $this->seed(VelvetMasterOperationalPermissionsSeeder::class);

        $this->assertSame(1, Permission::query()->where('slug', 'sessions.manage')->count());
        $this->assertSame(1, Permission::query()->where('slug', 'platforms.manage')->count());
        $this->assertTrue($manager->fresh()->permissions()->where('slug', 'local.custom_permission')->exists());
        $this->assertTrue($manager->fresh()->permissions()->where('slug', 'sessions.manage')->exists());
        $this->assertSame(1, $manager->fresh()->permissions()->where('slug', 'sessions.manage')->count());
    }

    public function test_assignments_follow_minimum_privilege_and_spatie_uses_the_default_guard(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VelvetMasterOperationalPermissionsSeeder::class);

        $manager = User::factory()->create()->assignRole('manager');
        $monitor = User::factory()->create()->assignRole('monitor');
        $finance = User::factory()->create()->assignRole('finance');
        $recruiter = User::factory()->create()->assignRole('recruiter');

        $this->assertTrue($manager->hasPermissionTo('sessions.manage'));
        $this->assertFalse($manager->hasPermissionTo('studio_accounts.manage'));
        $this->assertTrue($monitor->hasPermissionTo('productivity.validate_assigned'));
        $this->assertTrue($monitor->hasPermissionTo('studio_accounts.assign_assigned'));
        $this->assertFalse($monitor->hasPermissionTo('studio_accounts.manage'));
        $this->assertFalse($monitor->hasPermissionTo('productivity.manage'));
        $this->assertTrue($finance->hasPermissionTo('integrations.finance_export'));
        $this->assertFalse($finance->hasPermissionTo('platforms.manage'));
        $this->assertFalse($recruiter->hasPermissionTo('platforms.view'));
        $this->assertSame('web', config('auth.defaults.guard'));
        $this->assertTrue(Schema::hasColumn('permissions', 'guard_name'));
        $this->assertDatabaseHas('permissions', ['slug' => 'sessions.manage', 'guard_name' => 'web']);
    }

    public function test_users_without_operational_permissions_cannot_access_new_modules(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VelvetMasterOperationalPermissionsSeeder::class);
        $recruiter = User::factory()->create()->assignRole('recruiter');
        $monitor = User::factory()->create()->assignRole('monitor');

        $this->actingAs($recruiter)->get('/admin/platforms')->assertForbidden();
        $this->actingAs($recruiter)->get('/admin/productivity')->assertForbidden();
        $this->actingAs($monitor)->post('/admin/productivity/sessions', [
            'candidate_id' => 1,
            'started_at' => now()->format('Y-m-d H:i:s'),
            'source' => 'MANUAL',
        ])->assertForbidden();
    }
}
