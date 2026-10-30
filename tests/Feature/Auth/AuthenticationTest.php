<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.recruitment', absolute: false));
    }

    public function test_login_returns_to_agent_authorization_flow(): void
    {
        $user = User::factory()->create();

        $this->get('/oauth/agent/authorize?client_id=velvet-agent')
            ->assertRedirect(route('login'));

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/oauth/agent/authorize?client_id=velvet-agent');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_a_new_user_is_redirected_to_its_role_dashboard_after_logout(): void
    {
        $firstUser = User::factory()->create();
        $model = User::factory()->create();
        Role::query()->create([
            'name' => 'Modelo',
            'slug' => 'model',
            'description' => 'Acceso al portal de modelo.',
            'guard_name' => 'web',
        ]);
        $model->assignRole('model');

        $this->actingAs($firstUser)->post('/logout')->assertRedirect(route('login'));

        $response = $this->post('/login', [
            'email' => $model->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($model);
        $response->assertRedirect(route('model.dashboard', absolute: false));
    }
}
