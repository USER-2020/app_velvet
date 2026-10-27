<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VelvetMasterOperationalPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentAdministrationPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, VelvetMasterOperationalPermissionsSeeder::class]);
        config(['agent.studios' => ['studio-bogota']]);
    }

    public function test_admin_and_super_admin_can_view_agent_panel_but_other_roles_cannot(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $superAdmin = User::factory()->create()->assignRole('super_admin');
        $manager = User::factory()->create()->assignRole('manager');

        $this->actingAs($admin)->get('/admin/agent')->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Agent/Index'));
        $this->actingAs($superAdmin)->get('/admin/agent')->assertOk();
        $this->actingAs($manager)->get('/admin/agent')->assertForbidden();
    }

    public function test_admin_can_generate_a_single_use_panel_token(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $response = $this->actingAs($admin)
            ->post('/admin/agent/enrollment-tokens/panel', ['studioId' => 'studio-bogota'])
            ->assertRedirect()
            ->assertSessionHas('agentToken');

        $this->assertDatabaseHas('agent_enrollment_tokens', [
            'studio_id' => 'studio-bogota',
            'issued_by' => $admin->id,
            'used_at' => null,
        ]);
        $this->assertStringStartsWith('vea_', $response->getSession()->get('agentToken')['token']);
    }
}
