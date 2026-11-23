<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidedToursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_super_admin_can_manage_global_guided_tours(): void
    {
        $superAdmin = User::factory()->create()->assignRole('super_admin');
        $recruiter = User::factory()->create()->assignRole('recruiter');

        $this->actingAs($recruiter)
            ->get('/admin/guided-tours')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get('/admin/guided-tours')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/GuidedTours/Index')
                ->where('enabled', true));
    }

    public function test_super_admin_can_disable_guided_tours_for_everyone(): void
    {
        $superAdmin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->put('/admin/guided-tours', ['enabled' => false])
            ->assertRedirect('/admin/guided-tours');

        $this->assertFalse(SystemSetting::guidedToursEnabled());
        $this->assertDatabaseHas('system_settings', ['key' => 'guided_tours']);
    }

    public function test_functional_documentation_tour_saves_progress_per_user(): void
    {
        $monitor = User::factory()->create()->assignRole('monitor');
        $path = '/tour-progress/documentation-flows';

        $this->actingAs($monitor)->put($path, ['version' => 1, 'status' => 'completed'])->assertNoContent();
        $this->assertDatabaseHas('user_tour_progresses', [
            'user_id' => $monitor->id,
            'tour_key' => 'documentation-flows',
            'version' => 1,
        ]);

        SystemSetting::setGuidedToursEnabled(false);
        $this->put($path, ['version' => 1, 'status' => 'skipped'])->assertForbidden();
    }
}
