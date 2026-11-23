<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['groq.api_key' => 'test-groq-key']);
    }

    public function test_user_without_ai_permission_cannot_chat(): void
    {
        $user = User::factory()->create()->assignRole('candidate');

        $this->actingAs($user)->postJson('/ai/chat', ['message' => 'Hola'])
            ->assertForbidden();
    }

    public function test_admin_and_super_admin_have_chatbot_permissions(): void
    {
        foreach (['admin', 'super_admin'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();

            $this->assertTrue($role->hasPermissionTo('ai.chat'));
            $this->assertTrue($role->hasPermissionTo('ai.manage'));
        }

        $this->assertSame(1, Permission::where('slug', 'ai.chat')->count());
        $this->assertSame(1, Permission::where('slug', 'ai.manage')->count());
    }

    public function test_super_admin_can_enable_assistant_and_chat_uses_server_context(): void
    {
        $user = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($user)->put('/admin/ai-assistant', [
            'enabled' => true,
            'model' => 'llama-3.1-8b-instant',
            'models' => ['llama-3.1-8b-instant', 'openai/gpt-oss-20b'],
            'auto_select' => true,
            'max_tokens' => 500,
            'temperature' => 0.2,
        ])->assertRedirect('/admin/ai-assistant');

        Http::fake(['https://api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Respuesta de prueba.']]],
        ])]);

        $this->actingAs($user)->postJson('/ai/chat', [
            'message' => '¿Qué puedo hacer?',
            'path' => '/admin/recruitment',
        ])->assertOk()->assertJson(['message' => 'Respuesta de prueba.']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && $request->header('Authorization') === ['Bearer test-groq-key']
                && str_contains($request['messages'][0]['content'], 'Super Administrador')
                && str_contains($request['messages'][0]['content'], 'Pipeline');
        });
        $this->assertSame(['enabled' => true, 'model' => 'llama-3.1-8b-instant', 'models' => ['llama-3.1-8b-instant', 'openai/gpt-oss-20b'], 'auto_select' => true, 'max_tokens' => 500, 'temperature' => 0.2], SystemSetting::where('key', 'groq_assistant')->value('value'));
    }

    public function test_automatic_selection_uses_complex_model_and_falls_back_after_rate_limit(): void
    {
        $user = User::factory()->create()->assignRole('super_admin');
        SystemSetting::create(['key' => 'groq_assistant', 'value' => [
            'enabled' => true,
            'model' => 'llama-3.1-8b-instant',
            'models' => ['llama-3.1-8b-instant', 'openai/gpt-oss-20b', 'openai/gpt-oss-120b'],
            'auto_select' => true,
            'max_tokens' => 500,
            'temperature' => 0.2,
        ]]);
        $models = [];
        Http::fake(function ($request) use (&$models) {
            $models[] = $request['model'];

            return count($models) === 1
                ? Http::response([], 429)
                : Http::response(['choices' => [['message' => ['content' => 'Fallback listo.']]]]);
        });

        $this->actingAs($user)->postJson('/ai/chat', [
            'message' => 'Analiza y compara los permisos de estos dos perfiles con un resumen detallado.',
        ])->assertOk()->assertJson(['message' => 'Fallback listo.']);

        $this->assertSame(['openai/gpt-oss-20b', 'llama-3.1-8b-instant'], $models);
    }
}
