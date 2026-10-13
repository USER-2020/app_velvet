<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_inactive_accounts_are_rejected_even_when_they_hold_an_existing_session(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/profile')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_responses_include_baseline_security_headers(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
    }

    public function test_role_mutations_require_recent_password_confirmation_and_are_audited(): void
    {
        $admin = User::factory()->create()->assignRole('super_admin');
        $permission = Permission::query()->where('slug', 'leads.view')->firstOrFail();
        $payload = [
            'name' => 'Auditor interno',
            'slug' => 'auditor-interno',
            'permissions' => [$permission->id],
        ];

        $this->actingAs($admin)->post('/admin/access', $payload)
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/admin/access', $payload)
            ->assertRedirect('/admin/access');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'access.role_created',
            'actor_user_id' => $admin->id,
        ]);

        $log = AuditLog::query()->latest('id')->firstOrFail();
        $this->assertSame([$permission->id], $log->context['permission_ids']);
    }

    public function test_a_model_cannot_access_another_models_document_by_changing_the_id(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['is_active' => true])->assignRole('model');
        $owner = $this->candidateFor($user, 'owner');
        $other = $this->candidateFor(null, 'other');
        Storage::disk('local')->put('lead-documents/other.pdf', 'confidential');
        $document = LeadDocument::create([
            'lead_id' => $other->lead_id,
            'type' => 'contract_document',
            'original_name' => 'other.pdf',
            'path' => 'lead-documents/other.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12,
        ]);

        $this->actingAs($user)->get("/portal/documents/{$document->id}/preview")->assertForbidden();

        Storage::disk('local')->put('lead-documents/owner.pdf', 'owned');
        $ownDocument = LeadDocument::create([
            'lead_id' => $owner->lead_id,
            'type' => 'contract_document',
            'original_name' => 'owner.pdf',
            'path' => 'lead-documents/owner.pdf',
            'mime_type' => 'application/pdf',
            'size' => 5,
        ]);

        $this->actingAs($user)->get("/portal/documents/{$ownDocument->id}/preview")->assertOk();
    }

    private function candidateFor(?User $user, string $suffix): Candidate
    {
        $lead = Lead::create([
            'code' => "VEL-LEAD-{$suffix}",
            'status' => 'CONVERTED',
            'candidate_type' => 'MODEL',
            'first_name' => 'Modelo',
            'last_name' => $suffix,
            'sex' => 'WOMAN',
            'phone' => '3001234567',
            'email' => "{$suffix}@example.test",
            'country' => 'Colombia',
            'city' => 'Bogota',
            'birth_date' => '1998-01-01',
            'availability' => 'Tiempo completo',
            'work_mode' => 'En estudio',
        ]);

        return Candidate::create([
            'code' => "VEL-CAN-{$suffix}",
            'lead_id' => $lead->id,
            'user_id' => $user?->id,
            'candidate_type' => 'MODEL',
            'status' => 'ACTIVE',
        ]);
    }
}
