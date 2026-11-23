<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_roles_and_permissions(): void
    {
        $user = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($user)->get('/admin/access')->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Access/Index'));
    }

    public function test_super_admin_can_create_a_role_with_permissions(): void
    {
        $user = User::factory()->create()->assignRole('super_admin');
        $permission = Permission::where('slug', 'leads.view')->firstOrFail();

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->post('/admin/access', [
            'name' => 'Coordinador',
            'slug' => 'coordinador',
            'description' => 'Coordina la operación.',
            'permissions' => [$permission->id],
        ])->assertRedirect('/admin/access');

        $this->assertDatabaseHas('roles', ['slug' => 'coordinador']);
        $this->assertDatabaseHas('role_has_permissions', ['role_id' => Role::where('slug', 'coordinador')->value('id'), 'permission_id' => $permission->id]);
    }

    public function test_user_can_receive_a_role(): void
    {
        $admin = User::factory()->create()->assignRole('super_admin');
        $target = User::factory()->create();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put("/admin/users/{$target->id}", ['roles' => ['recruiter']])->assertRedirect('/admin/users');

        $this->assertTrue($target->fresh()->hasRole('recruiter'));
    }

    public function test_user_without_permission_cannot_manage_roles(): void
    {
        $user = User::factory()->create()->assignRole('hr');

        $this->actingAs($user)->get('/admin/access')->assertForbidden();
    }

    public function test_admin_cannot_edit_super_admin_role(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $superAdmin = Role::where('slug', 'super_admin')->firstOrFail();

        $this->actingAs($admin)->get("/admin/access/{$superAdmin->id}/edit")
            ->assertForbidden();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put("/admin/access/{$superAdmin->id}", [
            'name' => 'Super Administrador alterado',
            'slug' => 'super_admin',
            'description' => 'No debe cambiarse.',
            'permissions' => [],
        ])->assertForbidden();
    }

    public function test_admin_does_not_see_super_admin_role_in_role_list(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)->get('/admin/access')
            ->assertInertia(fn ($page) => $page
                ->where('roles', fn ($roles) => collect($roles)->where('slug', 'super_admin')->isEmpty())
            );
    }
}
