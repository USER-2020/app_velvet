<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\ContractSlot;
use App\Models\ContractInvitation;
use App\Models\User;
use App\Mail\PrequalificationFormMail;
use App\Mail\PrequalificationCompletedMail;
use App\Mail\InterviewInvitationMail;
use App\Mail\InterviewScheduledMail;
use App\Mail\ContractingInvitationMail;
use App\Mail\ContractingScheduledMail;
use Carbon\Carbon;
use App\Models\InterviewSlot;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function applicationPayload(string $type = 'MODEL'): array
    {
        return ['candidate_type' => $type, 'first_name' => 'Laura', 'last_name' => 'Gomez', 'sex' => 'WOMAN', 'phone' => '3001234567', 'email' => "laura-{$type}@example.com", 'country' => 'Colombia', 'city' => 'Bogota', 'birth_date' => '1998-01-01', 'experience' => 'Experiencia previa', 'experience_years' => '2', 'speaks_english' => true, 'english_level' => 'B2', 'availability' => 'Tiempo completo', 'work_mode' => 'En estudio', 'source' => 'Instagram', 'data_consent' => true];
    }

    public function test_public_model_application_creates_a_lead_with_tracking(): void
    {
        $response = $this->withHeaders(['referer' => 'https://instagram.com/velvet'])->post('/apply?utm_source=instagram&utm_campaign=talent', $this->applicationPayload());
        $response->assertOk();
        $this->assertDatabaseHas('leads', ['candidate_type' => 'MODEL', 'email' => 'laura-MODEL@example.com', 'utm_source' => 'instagram', 'utm_campaign' => 'talent']);
        $this->assertDatabaseHas('lead_activities', ['type' => 'created']);
    }

    public function test_public_legal_pages_are_available_without_authentication(): void
    {
        $this->get('/politica-de-privacidad')->assertOk()->assertInertia(fn ($page) => $page->component('Public/PrivacyPolicy'));
        $this->get('/terminos-y-condiciones')->assertOk()->assertInertia(fn ($page) => $page->component('Public/TermsAndConditions'));
    }

    public function test_monitor_application_creates_a_monitor_lead(): void
    {
        $this->post('/apply', $this->applicationPayload('MONITOR'))->assertOk();
        $this->assertDatabaseHas('leads', ['candidate_type' => 'MONITOR']);
    }

    public function test_monitor_application_only_allows_studio_work_mode(): void
    {
        $payload = $this->applicationPayload('MONITOR');
        $payload['work_mode'] = 'Desde casa';

        $this->from('/apply')->post('/apply', $payload)->assertRedirect('/apply')->assertSessionHasErrors('work_mode');
    }

    public function test_authenticated_recruiter_can_convert_a_lead_only_once(): void
    {
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000001', 'status' => 'NEW', ...$payload]);
        $user = User::factory()->create()->assignRole('recruiter');
        $this->actingAs($user)->post("/admin/leads/{$lead->id}/convert")->assertRedirect();
        $this->assertDatabaseHas('candidates', ['lead_id' => $lead->id, 'candidate_type' => 'MODEL']);
        // Repetir la petición es seguro si la primera navegación quedó
        // interrumpida después de confirmar la conversión.
        $this->actingAs($user)->post("/admin/leads/{$lead->id}/convert")->assertRedirect();
        $this->assertDatabaseCount('candidates', 1);
    }

    public function test_authenticated_recruiter_can_load_the_real_dashboard_data(): void
    {
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        Lead::create(['code' => 'VEL-LEAD-000002', 'status' => 'NEW', ...$payload]);
        $user = User::factory()->create()->assignRole('recruiter');

        $response = $this->actingAs($user)->get('/admin/recruitment?period=30d&type=MODEL');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Recruitment/Dashboard')
            ->has('metrics', 6)
            ->has('pipeline', 8)
            ->has('chart')
            ->where('filters.type', 'MODEL'));
    }

    public function test_workflow_board_reflects_the_current_contracting_and_activation_stages(): void
    {
        $user = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($user)
            ->get('/admin/workflows')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Recruitment/Workflows/Index')
                ->where('flows.0.nodes.6.key', 'WAITING')
                ->where('flows.0.nodes.7.key', 'CONTRACTING')
                ->where('flows.0.nodes.8.key', 'ONBOARDING')
                ->where('flows.1.nodes.0.key', 'ONBOARDING')
                ->where('flows.1.branches.0.from', 'ONBOARDING'));
    }

    public function test_model_directory_only_shows_admitted_candidates(): void
    {
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);

        $newLead = Lead::create(['code' => 'VEL-LEAD-000003', 'status' => 'NEW', ...$payload]);
        $admittedLead = Lead::create(['code' => 'VEL-LEAD-000004', 'status' => 'CONVERTED', ...$payload, 'email' => 'admitted@example.com']);

        Candidate::create([
            'code' => 'VEL-CAN-000001',
            'lead_id' => $newLead->id,
            'candidate_type' => 'MODEL',
            'status' => 'NEW',
        ]);
        $admittedCandidate = Candidate::create([
            'code' => 'VEL-CAN-000002',
            'lead_id' => $admittedLead->id,
            'candidate_type' => 'MODEL',
            'status' => 'ADMITTED',
        ]);

        $user = User::factory()->create()->assignRole('recruiter');

        $this->actingAs($user)
            ->get('/admin/candidates?type=MODEL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Recruitment/Candidates/Index')
                ->has('candidates.data', 1)
                ->where('candidates.data.0.id', $admittedCandidate->id));
    }

    public function test_recruiter_can_send_and_receive_prequalification_form(): void
    {
        Mail::fake();
        Storage::fake('local');

        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000005', 'status' => 'CONVERTED', ...$payload]);
        $candidate = Candidate::create([
            'code' => 'VEL-CAN-000003',
            'lead_id' => $lead->id,
            'candidate_type' => 'MODEL',
            'status' => 'NEW',
        ]);
        $user = User::factory()->create()->assignRole('recruiter');

        $this->actingAs($user)->post("/admin/candidates/{$candidate->id}/prequalification")->assertRedirect();
        Mail::assertSent(PrequalificationFormMail::class, function (PrequalificationFormMail $mail) use ($candidate) {
            return $mail->candidate->is($candidate) && str_contains($mail->formUrl, '/prequalification/');
        });
        $candidate->refresh();
        $this->assertSame('CONTACTED', $candidate->status->value);

        $formUrl = null;
        Mail::assertSent(PrequalificationFormMail::class, function (PrequalificationFormMail $mail) use (&$formUrl) {
            $formUrl = $mail->formUrl;

            return true;
        });
        $this->get($formUrl)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Public/Prequalification')
            ->where('candidate.initial.availability', 'Tiempo completo')
            ->where('candidate.initial.work_mode', 'En estudio')
            ->where('candidate.initial.experience', 'Experiencia previa'));

        $this->post($formUrl, [
            'availability' => 'Tiempo completo',
            'work_mode' => 'Desde casa',
            'experience' => 'Tengo experiencia comprobable en creación de contenido.',
            'motivation' => 'Quiero crecer profesionalmente con el equipo Velvet.',
            'portfolio_url' => 'https://example.com/portfolio',
            'has_equipment' => true,
            'identity_document' => UploadedFile::fake()->create('documento.pdf', 200, 'application/pdf'),
            'accept_terms' => true,
        ])->assertRedirect('/prequalification/completada');

        $this->get('/prequalification/completada')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/PrequalificationSuccess')
                ->where('candidate.code', $candidate->code));

        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'status' => 'PREQUALIFIED']);
        $this->assertDatabaseHas('lead_documents', ['lead_id' => $lead->id, 'original_name' => 'documento.pdf']);
        $this->assertDatabaseHas('candidate_activities', ['candidate_id' => $candidate->id, 'type' => 'prequalification_completed']);
        Mail::assertSent(PrequalificationCompletedMail::class, function (PrequalificationCompletedMail $mail) use ($candidate) {
            return $mail->candidate->is($candidate);
        });
    }

    public function test_recruiter_can_edit_and_discard_a_lead_with_a_reason(): void
    {
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000006', 'status' => 'NEW', ...$payload]);
        $user = User::factory()->create()->assignRole('recruiter');

        $this->actingAs($user)->put("/admin/leads/{$lead->id}", [...$payload, 'first_name' => 'Laura corregida'])->assertRedirect();
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'first_name' => 'Laura corregida']);

        $this->actingAs($user)->post("/admin/leads/{$lead->id}/discard", ['reason' => 'Datos de contacto inválidos.'])->assertRedirect('/admin/leads');
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'DISCARDED', 'discard_reason' => 'Datos de contacto inválidos.']);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'discarded']);
    }

    public function test_recruiter_can_edit_and_discard_a_candidate_with_a_reason(): void
    {
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000007', 'status' => 'CONVERTED', ...$payload]);
        $candidate = Candidate::create(['code' => 'VEL-CAN-000004', 'lead_id' => $lead->id, 'candidate_type' => 'MODEL', 'status' => 'NEW']);
        $user = User::factory()->create()->assignRole('recruiter');

        $this->actingAs($user)->put("/admin/candidates/{$candidate->id}", [...$payload, 'first_name' => 'Candidata corregida'])->assertRedirect();
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'first_name' => 'Candidata corregida']);

        $this->actingAs($user)->post("/admin/candidates/{$candidate->id}/discard", ['reason' => 'No aprobó la verificación.'])->assertRedirect();
        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'status' => 'DISCARDED', 'discard_reason' => 'No aprobó la verificación.']);
        $this->assertDatabaseHas('candidate_activities', ['candidate_id' => $candidate->id, 'type' => 'discarded']);
    }

    public function test_candidate_can_choose_an_interview_slot_from_the_email(): void
    {
        Mail::fake();
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000008', 'status' => 'CONVERTED', ...$payload]);
        $candidate = Candidate::create(['code' => 'VEL-CAN-000005', 'lead_id' => $lead->id, 'candidate_type' => 'MODEL', 'status' => 'PREQUALIFIED']);
        $user = User::factory()->create()->assignRole('recruiter');
        $date = Carbon::now('America/Bogota')->addDay()->toDateString();
        $weekday = (string) Carbon::parse($date, 'America/Bogota')->dayOfWeekIso;

        $this->actingAs($user)->post('/admin/interviews/slots', ['from_date' => $date, 'to_date' => $date, 'weekdays' => [$weekday], 'start_time' => '09:00', 'end_time' => '11:00'])->assertRedirect();
        $this->actingAs($user)->post("/admin/candidates/{$candidate->id}/interview-invitation")->assertRedirect();
        Mail::assertSent(InterviewInvitationMail::class);
        $url = null;
        Mail::assertSent(InterviewInvitationMail::class, function (InterviewInvitationMail $mail) use (&$url) { $url = $mail->bookingUrl; return true; });
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Public/InterviewBooking')->has('slots', 2));
        $slot = InterviewSlot::first();
        $this->post($url, ['slot_id' => $slot->id])->assertRedirect('/entrevista/completada');
        $this->get('/entrevista/completada')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/InterviewBookingSuccess')
                ->where('candidate.code', $candidate->code));
        $this->assertDatabaseHas('interview_slots', ['id' => $slot->id, 'status' => 'BOOKED']);
        $this->assertDatabaseHas('interviews', ['candidate_id' => $candidate->id, 'status' => 'SCHEDULED', 'interview_slot_id' => $slot->id]);
        Mail::assertSent(InterviewScheduledMail::class);
    }

    public function test_moving_a_prequalified_candidate_to_interview_sends_the_booking_form_automatically(): void
    {
        Mail::fake();
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000009', 'status' => 'CONVERTED', ...$payload]);
        $candidate = Candidate::create(['code' => 'VEL-CAN-000006', 'lead_id' => $lead->id, 'candidate_type' => 'MODEL', 'status' => 'PREQUALIFIED']);
        $user = User::factory()->create()->assignRole('recruiter');
        $date = Carbon::now('America/Bogota')->addDay()->toDateString();
        $weekday = (string) Carbon::parse($date, 'America/Bogota')->dayOfWeekIso;

        $this->actingAs($user)->post('/admin/interviews/slots', [
            'from_date' => $date,
            'to_date' => $date,
            'weekdays' => [$weekday],
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertRedirect();

        $this->actingAs($user)->patch("/admin/candidates/{$candidate->id}/status", ['status' => 'INTERVIEW'])->assertRedirect();

        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'status' => 'INTERVIEW']);
        $this->assertDatabaseHas('interviews', ['candidate_id' => $candidate->id, 'status' => 'INVITED']);
        Mail::assertSent(InterviewInvitationMail::class, fn (InterviewInvitationMail $mail) => $mail->candidate->is($candidate));
    }

    public function test_public_contracting_uploads_are_guided_and_saved_by_requirement(): void
    {
        Storage::fake('local');
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000010', 'status' => 'CONVERTED', ...$payload]);
        $candidate = Candidate::create(['code' => 'VEL-CAN-000007', 'lead_id' => $lead->id, 'candidate_type' => 'MODEL', 'status' => 'CONTRACTING']);
        $url = URL::temporarySignedRoute('contracting.documents.show', now()->addHour(), ['candidate' => $candidate, 'token' => 'documents-secure-token']);

        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Public/ContractingDocuments')
            ->has('requirements', 4)
            ->where('requirements.0.key', 'identity_document')
            ->where('requirements.0.uploaded', false));

        $documents = [
            'identity_document' => UploadedFile::fake()->create('identidad.pdf', 100, 'application/pdf'),
            'bank_certificate' => UploadedFile::fake()->create('banco.pdf', 100, 'application/pdf'),
            'tax_document' => UploadedFile::fake()->create('rut.pdf', 100, 'application/pdf'),
        ];

        foreach ($documents as $key => $upload) {
            $this->post($url, ['documents' => [$key => $upload], 'finalize' => false])->assertRedirect();
        }

        $finalResponse = $this->post($url, ['documents' => [], 'finalize' => true])->assertRedirect();
        $successUrl = $finalResponse->headers->get('Location');
        $this->assertStringContainsString('/documentos/exito', $successUrl);
        $this->get($successUrl)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Public/ContractingDocumentsSuccess')
            ->where('candidate.code', 'VEL-CAN-000007')
            ->has('documents', 3)
            ->where('realtime.channel', 'candidate-documents-' . $candidate->id));

        $this->assertDatabaseHas('lead_documents', ['lead_id' => $lead->id, 'original_name' => 'identidad.pdf']);
        $document = LeadDocument::query()->where('lead_id', $lead->id)->where('original_name', 'identidad.pdf')->firstOrFail();
        $this->assertSame('identity_document', $document->metadata['requirement_key']);
        Storage::disk('local')->assertExists($document->path);
        $this->assertDatabaseHas('candidate_activities', ['candidate_id' => $candidate->id, 'type' => 'candidate_documents_uploaded']);

        $previewUrl = URL::temporarySignedRoute('contracting.documents.preview', now()->addMinutes(10), ['candidate' => $candidate, 'token' => 'document-preview', 'document' => $document]);
        $this->get($previewUrl)->assertOk();

        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->where('requirements.0.uploaded', true)
            ->where('requirements.0.status', 'PENDING')
            ->has('requirements.0.file.preview_url')
            ->where('requirements.2.uploaded', true));

        $document->update(['status' => 'REJECTED', 'review_note' => 'La imagen debe verse completa y sin reflejos.']);

        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->where('requirements.0.complete', false)
            ->where('requirements.0.status', 'REJECTED')
            ->where('requirements.0.review_note', 'La imagen debe verse completa y sin reflejos.'));
    }

    public function test_admin_can_preview_a_stored_document(): void
    {
        Storage::fake('local');
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000012', 'status' => 'CONVERTED', ...$payload]);
        $document = LeadDocument::create([
            'lead_id' => $lead->id,
            'type' => 'contract_document',
            'original_name' => 'identidad.pdf',
            'path' => 'lead-documents/identidad.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12,
            'status' => 'PENDING',
        ]);
        Storage::disk('local')->put($document->path, '%PDF-1.4 test');

        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->get("/admin/documents/{$document->id}/preview")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_verifying_all_contract_documents_sends_booking_agenda_once(): void
    {
        Mail::fake();
        $user = User::factory()->create()->assignRole('hr');
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000011', 'status' => 'CONVERTED', ...$payload]);
        $candidate = Candidate::create(['code' => 'VEL-CAN-000008', 'lead_id' => $lead->id, 'candidate_type' => 'MODEL', 'status' => 'WAITING']);
        ContractSlot::create(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(), 'status' => 'AVAILABLE', 'created_by' => $user->id]);

        $documents = collect(['identity_document', 'bank_certificate', 'tax_document'])->map(fn (string $key) => LeadDocument::create([
            'lead_id' => $lead->id,
            'type' => 'contract_document',
            'original_name' => $key . '.pdf',
            'path' => 'lead-documents/' . $key . '.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'status' => 'PENDING',
            'metadata' => ['requirement_key' => $key],
        ]));

        foreach ($documents as $document) {
            $this->actingAs($user)->patch("/admin/documents/{$document->id}/status", ['status' => 'VERIFIED'])->assertRedirect();
        }

        $invitation = ContractInvitation::query()->where('candidate_id', $candidate->id)->firstOrFail();
        $this->assertSame('INVITED', $invitation->status);
        $bookingUrl = null;
        Mail::assertSent(ContractingInvitationMail::class, 1);
        Mail::assertSent(ContractingInvitationMail::class, function (ContractingInvitationMail $mail) use (&$bookingUrl, $candidate) {
            $bookingUrl = $mail->bookingUrl;
            return $mail->candidate->is($candidate);
        });
        $this->assertDatabaseHas('candidate_activities', ['candidate_id' => $candidate->id, 'type' => 'contracting_invited']);
        $this->get($bookingUrl)->assertOk()->assertInertia(fn ($page) => $page->component('Public/ContractingBooking')->has('slots', 1));
        $this->post($bookingUrl, ['slot_id' => ContractSlot::firstOrFail()->id])->assertRedirect(route('contracting.booking.success'));
        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'status' => 'CONTRACTING']);
        $this->assertDatabaseHas('contract_appointments', ['candidate_id' => $candidate->id, 'status' => 'SCHEDULED']);
        Mail::assertSent(ContractingScheduledMail::class, function (ContractingScheduledMail $mail) {
            $expectedStart = Carbon::createFromFormat('Y-m-d H:i:s', $mail->appointment->slot->getRawOriginal('starts_at'), 'UTC')->setTimezone('America/Bogota');

            return count($mail->attachments()) === 1
                && str_contains($mail->render(), $expectedStart->format('H:i'));
        });
    }

    public function test_verified_contract_documents_do_not_send_agenda_without_future_slots(): void
    {
        Mail::fake();
        $user = User::factory()->create()->assignRole('hr');
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000012', 'status' => 'CONVERTED', ...$payload]);
        $candidate = Candidate::create(['code' => 'VEL-CAN-000009', 'lead_id' => $lead->id, 'candidate_type' => 'MODEL', 'status' => 'WAITING']);

        foreach (['identity_document', 'bank_certificate', 'tax_document'] as $key) {
            $document = LeadDocument::create([
                'lead_id' => $lead->id,
                'type' => 'contract_document',
                'original_name' => $key . '.pdf',
                'path' => 'lead-documents/' . $key . '.pdf',
                'mime_type' => 'application/pdf',
                'size' => 100,
                'status' => 'PENDING',
                'metadata' => ['requirement_key' => $key],
            ]);

            $this->actingAs($user)->patch("/admin/documents/{$document->id}/status", ['status' => 'VERIFIED'])->assertRedirect();
        }

        $this->assertDatabaseMissing('contract_invitations', ['candidate_id' => $candidate->id]);
        Mail::assertNotSent(ContractingInvitationMail::class);
    }

    public function test_recruiter_can_send_agenda_from_waiting_candidate_after_documents_were_already_verified(): void
    {
        Mail::fake();
        $user = User::factory()->create()->assignRole('hr');
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000013', 'status' => 'CONVERTED', ...$payload]);
        $candidate = Candidate::create(['code' => 'VEL-CAN-000010', 'lead_id' => $lead->id, 'candidate_type' => 'MODEL', 'status' => 'WAITING']);
        ContractSlot::create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'status' => 'AVAILABLE', 'created_by' => $user->id]);

        foreach (['identity_document', 'bank_certificate', 'tax_document'] as $key) {
            LeadDocument::create([
                'lead_id' => $lead->id,
                'type' => 'contract_document',
                'original_name' => $key . '.pdf',
                'path' => 'lead-documents/' . $key . '.pdf',
                'mime_type' => 'application/pdf',
                'size' => 100,
                'status' => 'VERIFIED',
                'metadata' => ['requirement_key' => $key],
            ]);
        }

        $this->actingAs($user)->post("/admin/candidates/{$candidate->id}/contracting-invitation")->assertRedirect();

        $this->assertDatabaseHas('contract_invitations', ['candidate_id' => $candidate->id, 'status' => 'INVITED']);
        Mail::assertSent(ContractingInvitationMail::class);
    }

    public function test_recruiter_can_upload_signed_contract_documents_from_candidate_flow(): void
    {
        Storage::fake('local');
        $user = User::factory()->create()->assignRole('hr');
        $payload = $this->applicationPayload();
        unset($payload['data_consent']);
        $lead = Lead::create(['code' => 'VEL-LEAD-000014', 'status' => 'CONVERTED', ...$payload]);
        $candidate = Candidate::create(['code' => 'VEL-CAN-000011', 'lead_id' => $lead->id, 'candidate_type' => 'MONITOR', 'status' => 'CONTRACTING']);

        $response = $this->actingAs($user)->post("/admin/contracting/candidates/{$candidate->id}/documents", [
            'documents' => [UploadedFile::fake()->create('contrato-firmado.pdf', 120, 'application/pdf')],
        ]);

        $response->assertRedirect();
        $document = LeadDocument::query()->where('lead_id', $lead->id)->where('original_name', 'contrato-firmado.pdf')->firstOrFail();
        $this->assertSame('contract_document', $document->type);
        $this->assertSame('COMPLETED', $document->status);
        Storage::disk('local')->assertExists($document->path);
        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'status' => 'ONBOARDING',
        ]);
        $this->assertNotNull($candidate->fresh()->contracted_at);
        $this->assertDatabaseHas('candidate_activities', [
            'candidate_id' => $candidate->id,
            'type' => 'contract_documents_uploaded',
        ]);
    }
}
