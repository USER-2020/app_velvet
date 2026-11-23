<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Lead;
use App\Models\ModelMonitorAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModelPlanningAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_the_global_model_planning_directory(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->get('/admin/model-planning')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/ModelPlanning/Index'));
    }

    public function test_admin_sees_all_wishlists_and_monitor_only_sees_assigned_models(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $monitorUser = User::factory()->create()->assignRole('monitor');
        $monitorLead = Lead::create([
            'code' => 'LEAD-MONITOR-001', 'candidate_type' => 'MONITOR', 'first_name' => 'Monitor',
            'last_name' => 'Prueba', 'phone' => '3000000001', 'email' => 'monitor@example.test',
            'city' => 'Bogota', 'birth_date' => '1990-01-01', 'availability' => 'Tiempo completo',
            'work_mode' => 'En estudio', 'status' => 'CONVERTED',
        ]);
        $monitor = Candidate::create([
            'code' => 'CAN-MONITOR-001', 'lead_id' => $monitorLead->id, 'user_id' => $monitorUser->id,
            'candidate_type' => 'MONITOR', 'status' => 'ACTIVE', 'activated_at' => now(),
        ]);

        $models = collect(['001', '002'])->map(function (string $suffix) {
            $user = User::factory()->create()->assignRole('model');
            $lead = Lead::create([
                'code' => 'LEAD-MODEL-'.$suffix, 'candidate_type' => 'MODEL', 'first_name' => 'Modelo',
                'last_name' => $suffix, 'phone' => '30000000'.$suffix, 'email' => 'model'.$suffix.'@example.test',
                'city' => 'Bogota', 'birth_date' => '1998-01-01', 'availability' => 'Tiempo completo',
                'work_mode' => 'En estudio', 'status' => 'CONVERTED',
            ]);
            $candidate = Candidate::create([
                'code' => 'CAN-MODEL-'.$suffix, 'lead_id' => $lead->id, 'user_id' => $user->id,
                'candidate_type' => 'MODEL', 'status' => 'ACTIVE', 'activated_at' => now(),
            ]);
            $candidate->wishlistItems()->create(['title' => 'Deseo '.$suffix, 'currency' => 'COP', 'priority' => 'medium', 'status' => 'planned']);
            return $candidate;
        });
        ModelMonitorAssignment::create(['model_candidate_id' => $models->first()->id, 'monitor_candidate_id' => $monitor->id, 'assigned_at' => now()]);

        $this->actingAs($admin)->get('/admin/model-planning')->assertInertia(fn ($page) => $page
            ->has('allWishlists', 2)
            ->has('models', 2)
            ->where('selectedModel', null));
        $this->actingAs($monitorUser)->get('/admin/model-planning')->assertInertia(fn ($page) => $page
            ->has('allWishlists', 1)
            ->has('models', 1)
            ->where('allWishlists.0.model.id', $models->first()->id));
    }

    public function test_user_without_team_permission_cannot_open_the_planning_directory(): void
    {
        $recruiter = User::factory()->create()->assignRole('recruiter');

        $this->actingAs($recruiter)->get('/admin/model-planning')->assertForbidden();
    }

    public function test_model_can_store_private_wishlist_image_and_notify_admins(): void
    {
        Storage::fake('local');
        $modelUser = User::factory()->create(['is_active' => true]);
        $admin = User::factory()->create()->assignRole('admin');
        $modelUser->assignRole('model');
        $lead = Lead::create([
            'code' => 'LEAD-WISHLIST-001', 'candidate_type' => 'MODEL', 'first_name' => 'Laura',
            'last_name' => 'Wishlist', 'phone' => '3000000000', 'email' => 'wishlist@example.test',
            'city' => 'Bogota', 'birth_date' => '1998-01-01', 'availability' => 'Tiempo completo',
            'work_mode' => 'En estudio', 'status' => 'CONVERTED',
        ]);
        $candidate = Candidate::create([
            'code' => 'CAN-WISHLIST-001', 'lead_id' => $lead->id, 'user_id' => $modelUser->id,
            'candidate_type' => 'MODEL', 'status' => 'ACTIVE', 'activated_at' => now(),
        ]);

        $response = $this->actingAs($modelUser)->post('/model/planning/wishlist', [
            'title' => 'Cámara nueva', 'category' => 'Tecnología', 'description' => 'Una cámara',
            'currency' => 'COP', 'priority' => 'high', 'status' => 'planned',
            'image_file' => UploadedFile::fake()->image('camera.png'),
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('wishlist_items', ['candidate_id' => $candidate->id, 'title' => 'Cámara nueva']);
        $item = $candidate->wishlistItems()->firstOrFail();
        Storage::disk('local')->assertExists($item->image_path);
        $this->assertStringNotContainsString('/storage/', $item->image_path);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'wishlist.created',
            'channel' => 'admin-leads',
        ]);

        $oldPath = $item->image_path;
        $this->actingAs($modelUser)->post('/model/planning/wishlist/'.$item->id, [
            '_method' => 'patch', 'title' => 'Cámara nueva actualizada', 'currency' => 'COP',
            'priority' => 'medium', 'status' => 'planned',
            'image_file' => UploadedFile::fake()->image('camera-updated.png'),
        ])->assertRedirect();
        $item->refresh();
        $this->assertNotSame($oldPath, $item->image_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($item->image_path);
        $this->actingAs($modelUser)->get('/planning/wishlist/'.$item->id.'/image?v='.$item->updated_at->timestamp)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $share = $this->actingAs($modelUser)->postJson('/model/planning/share')->assertOk()->json('url');
        $token = last(explode('/', parse_url($share, PHP_URL_PATH)));
        $this->get('/wishlist/share/'.$token)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Public/Wishlist/Show')
            ->has('wishlist', 1)
            ->where('wishlist.0.image_url', url('/wishlist/share/'.$token.'/image/'.$item->id)));
        $this->get('/wishlist/share/'.$token.'/image/'.$item->id)->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->actingAs($admin)->post('/planning/strategies', [
            'candidate_id' => $candidate->id,
            'title' => 'Meta de cámara', 'type' => 'wishlist_goal',
            'description' => 'Plan de ingresos y fidelización.', 'currency' => 'COP',
            'urgency' => 'medium', 'status' => 'draft',
            'metadata' => ['wishlist_item_ids' => [$item->id], 'horizon' => '3_months'],
        ])->assertRedirect();
        $this->assertDatabaseHas('model_projections', ['candidate_id' => $candidate->id, 'title' => 'Meta de cámara']);
    }
}
