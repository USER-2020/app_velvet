<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_super_admin_can_publish_documentation(): void
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

    public function test_queue_operations_reference_is_available_in_the_documentation_center(): void
    {
        $superAdmin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->get('/admin/documentation/references/queue-operations')
            ->assertRedirect(route('admin.documentation.index', ['reference' => 'queue-operations']));

        $this->get('/admin/documentation?reference=queue-operations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('referencePreview.title', 'Colas y scheduler en producción')
                ->where('referencePreview.path', 'OPERACION_COLAS_Y_SCHEDULER_PRODUCCION.md'));
    }

    public function test_each_seeded_role_receives_a_functional_guide_profile(): void
    {
        $catalog = require resource_path('documentation/dashboard.php');
        foreach ($catalog['roles'] as $slug => $summary) {
            $user = User::factory()->create()->assignRole($slug);

            $this->actingAs($user)->get('/admin/documentation')->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/Documentation/Index')
                    ->where('guideProfiles.0.summary', $summary)
                    ->has('guides')
                    ->where('canManage', $slug === 'super_admin'));
        }
    }

    public function test_guides_and_demos_follow_module_and_action_permissions(): void
    {
        $base = '/admin/documentation';
        $this->get($base.'/guides/leads')->assertRedirect(route('login'));

        $model = User::factory()->create()->assignRole('model');
        $this->actingAs($model)->get($base)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('guides', function ($guides) {
                $keys = collect($guides)->pluck('key');

                return $keys->contains('model-planning') && ! $keys->contains('leads');
            }));
        $this->get($base.'/guides/leads?download=1')->assertNotFound();
        $this->get($base.'/guides/model-planning')->assertOk()
            ->assertSee('Reproducir');

        $recruiter = User::factory()->create()->assignRole('recruiter');
        $this->actingAs($recruiter)->get($base.'/guides/leads')->assertOk()
            ->assertSee('Convertir a candidato');
        $this->get($base.'/guides/documents')->assertOk()
            ->assertDontSee('Registrar la revisión');

        $hr = User::factory()->create()->assignRole('hr');
        $this->actingAs($hr)->get($base.'/guides/documents?download=1')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="velvet-documents.html"')
            ->assertSee('data-composition-id="velvet-documents"', false)
            ->assertSee('Registrar la revisión')
            ->assertDontSee('Reproducir');

        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->get($base.'/guides/documentation')->assertOk()
            ->assertDontSee('Publicar y mantener los anexos');
        $this->get($base.'/guides/guided-tours')->assertNotFound();
    }

    public function test_super_admin_catalog_covers_every_configured_module(): void
    {
        $user = User::factory()->create()->assignRole('super_admin');
        $catalog = require resource_path('documentation/dashboard.php');

        $this->actingAs($user)->get('/admin/documentation')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('guides', count($catalog['modules'])));
        $this->get('/admin/documentation/guides/documentation')->assertOk()
            ->assertSee('Publicar y mantener los anexos');
    }

    public function test_video_stream_respects_the_module_permissions(): void
    {
        $path = '/admin/documentation/guides/leads/video';
        $this->get($path)->assertRedirect(route('login'));

        $model = User::factory()->create()->assignRole('model');
        $this->actingAs($model)->get($path)->assertNotFound();

        $recruiter = User::factory()->create()->assignRole('recruiter');
        $this->actingAs($recruiter)->get($path, ['Range' => 'bytes=0-1023'])
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Content-Range', 'bytes 0-1023/'.filesize(resource_path('documentation/videos/leads.mp4')));
        $this->get('/admin/documentation/guides/unknown/video')->assertNotFound();
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
