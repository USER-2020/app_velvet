<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_documentation_is_exclusive_to_super_admin(): void
    {
        $superAdmin = User::factory()->create()->assignRole('super_admin');
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)->get('/admin/documentation')->assertOk();
        $this->actingAs($admin)->post('/admin/documentation', ['title' => 'No', 'category' => 'No'])->assertForbidden();
        $this->actingAs($superAdmin)->get('/admin/documentation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Documentation/Index')
                ->where('references.1.key', 'openapi'));
    }

    public function test_super_admin_can_create_and_download_documentation_attachment(): void
    {
        Storage::fake('local');
        $superAdmin = User::factory()->create()->assignRole('super_admin');
        $file = UploadedFile::fake()->create('manual.pdf', 12, 'application/pdf');

        $this->actingAs($superAdmin)->post('/admin/documentation', [
            'title' => 'Manual interno',
            'category' => 'Operación',
            'summary' => 'Procedimiento interno.',
            'content' => 'Paso uno.',
            'attachment' => $file,
        ])->assertRedirect();

        $this->assertDatabaseHas('platform_documentations', ['title' => 'Manual interno', 'attachment_name' => 'manual.pdf']);
        Storage::disk('local')->assertExists('platform-documentation/'.$file->hashName());
    }
}
