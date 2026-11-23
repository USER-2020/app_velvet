<?php

use App\Models\Candidate;
use App\Models\Lead;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$workspace = getenv('VELVET_RECORDING_DIR');
if (! $workspace || ! app()->environment('testing') || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== $workspace.'/demo.sqlite' || User::exists()) {
    throw new RuntimeException('La captura requiere una base SQLite temporal y vacía.');
}

(new Database\Seeders\TestUsersSeeder)->run();
(new Database\Seeders\TrainingDataSeeder)->run();
SystemSetting::setGuidedToursEnabled(false);
SystemSetting::create(['key' => 'groq_assistant', 'value' => ['enabled' => true]]);

$person = function ($name, $status = null, $type = 'MODEL', $user = null) {
    $lead = Lead::create([
        'code' => 'DEMO-'.strtoupper($name), 'candidate_type' => $type, 'first_name' => $name,
        'last_name' => 'Ejemplo', 'phone' => '+5730000000'.str_pad((string) (Lead::count() + 1), 2, '0', STR_PAD_LEFT),
        'email' => strtolower($name).'@velvet.test', 'city' => 'Bogotá', 'country' => 'Colombia',
        'birth_date' => '1999-04-15', 'sex' => 'FEMALE', 'experience' => 'Perfil ficticio para practicar.',
        'availability' => 'Tiempo completo', 'work_mode' => 'En estudio', 'status' => $status ? 'CONVERTED' : 'QUALIFIED',
    ]);

    return $status ? Candidate::create([
        'code' => 'CAN-'.strtoupper($name), 'lead_id' => $lead->id, 'user_id' => $user,
        'candidate_type' => $type, 'status' => $status,
    ]) : $lead;
};

$lead = $person('Sofía');
$candidate = $person('Andrea', 'EVALUATION');
$admitted = $person('Camila', 'ADMITTED');
$waiting = $person('Marta', 'WAITING');
$signing = $person('Elena', 'CONTRACTING');
$ready = $person('Lina', 'ONBOARDING');
$prequalification = $person('Julia', 'CONTACTED');
$interviewCandidate = $person('Ana', 'INTERVIEW');
$modelUser = User::whereEmail('modelo@velvet.test')->firstOrFail();
$monitorUser = User::whereEmail('monitor@velvet.test')->firstOrFail();
$model = $person('Valeria', 'ACTIVE', 'MODEL', $modelUser->id);
$monitor = $person('Daniel', 'ACTIVE', 'MONITOR', $monitorUser->id);

$insert = fn ($table, $data) => DB::table($table)->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
$insert('model_monitor_assignments', ['model_candidate_id' => $model->id, 'monitor_candidate_id' => $monitor->id]);
foreach ([$model, $monitor] as $profile) {
    $insert('operational_profiles', ['candidate_id' => $profile->id, 'profile_type' => $profile->candidate_type->value, 'operational_status' => 'ACTIVE']);
}
$insert('wishlist_items', ['candidate_id' => $model->id, 'title' => 'Lámpara para el estudio', 'category' => 'Equipo', 'description' => 'Ejemplo de iluminación para mejorar el espacio.', 'estimated_price' => 120000]);
$room = $insert('rooms', ['code' => 'DEMO-01', 'name' => 'Sala de práctica', 'description' => 'Reserva ficticia de muestra.']);
$insert('rooms', ['code' => 'DEMO-02', 'name' => 'Sala de grabación', 'description' => 'Sala disponible para una nueva reserva de prueba.']);
$admin = User::whereEmail('superadmin@velvet.test')->firstOrFail();
$insert('room_reservations', ['room_id' => $room, 'candidate_id' => $model->id, 'user_id' => $modelUser->id,
    'reserved_by' => $admin->id, 'purpose' => 'Sesión de práctica', 'starts_at' => now('UTC')->addMinutes(5), 'ends_at' => now('UTC')->addHours(2)]);
$platform = $insert('platform_catalogs', ['code' => 'chaturbate', 'name' => 'Chaturbate', 'adapter_key' => 'chaturbate']);
$insert('studio_accounts', ['platform_catalog_id' => $platform, 'name' => 'Studio Demo', 'provider_key' => 'chaturbate', 'connection_mode' => 'LIVE', 'status' => 'UNCONFIGURED']);
$insert('studio_accounts', ['platform_catalog_id' => $platform, 'name' => 'Cuenta simulada', 'connection_mode' => 'SIMULATED']);

foreach ([$waiting, $signing, $model] as $record) {
    $insert('lead_documents', ['lead_id' => $record->lead_id, 'type' => 'contract_document', 'original_name' => 'identidad-demo.pdf',
        'path' => 'demo/documento.pdf', 'mime_type' => 'application/pdf', 'size' => 1024,
        'status' => $record === $model ? 'VERIFIED' : 'PENDING', 'metadata' => json_encode(['requirement_key' => 'identity_document'])]);
}

$candidate->update(['didit_session_id' => 'demo-identidad-01', 'identity_verification_status' => 'APPROVED',
    'identity_verified_at' => now(), 'identity_verification_data' => ['age_verified' => true]]);
$expires = now()->addDays(7);
$token = 'demo-tutorial';
$prequalification->update(['prequalification_token_hash' => hash('sha256', $token), 'prequalification_expires_at' => $expires, 'prequalification_sent_at' => now()]);
$slot = $insert('interview_slots', ['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
$interview = $insert('interviews', ['candidate_id' => $interviewCandidate->id, 'invitation_token_hash' => hash('sha256', $token), 'invitation_expires_at' => $expires]);
$booking = $insert('contract_invitations', ['candidate_id' => $waiting->id, 'token_hash' => hash('sha256', $token), 'invitation_expires_at' => $expires]);
$insert('contract_slots', ['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);
$insert('notifications', ['type' => 'demo', 'user_id' => $admin->id, 'channel' => 'panel',
    'title' => 'Solicitud ficticia recibida', 'description' => 'Sofía Ejemplo envió un formulario.',
    'data' => json_encode(['lead_id' => $lead->id])]);
$insert('branches', ['code' => 'DEMO', 'name' => 'Sede de práctica']);
App\Models\Role::create(['slug' => 'demo-coordinacion', 'name' => 'Coordinación de práctica', 'guard_name' => 'web']);
$insert('operational_shifts', ['code' => 'DEMO-TR1', 'model_candidate_id' => $model->id, 'monitor_candidate_id' => $monitor->id,
    'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(2)]);

$catalog = require resource_path('documentation/dashboard.php');
$modules = [];
foreach ($catalog['modules'] as $key => [, $title, $route, , $steps]) {
    $modules[$key] = ['title' => $title, 'url' => route($route, $catalog['modules'][$key][5] ?? [], false), 'steps' => $steps];
}

echo json_encode([
    'modules' => $modules,
    'users' => ['admin' => $admin->id, 'monitor' => $monitorUser->id, 'model' => $modelUser->id],
    'urls' => [
        'lead' => '/admin/leads/'.$lead->id, 'candidate' => '/admin/candidates/'.$candidate->id,
        'admitted' => '/admin/candidates/'.$admitted->id, 'ready' => '/admin/candidates/'.$ready->id,
        'model' => '/admin/candidates/'.$model->id, 'monitor' => '/admin/candidates/'.$monitor->id,
        'signing' => '/admin/candidates/'.$signing->id, 'monitorModel' => '/monitor/models/'.$model->id,
        'prequalCandidate' => '/admin/candidates/'.$prequalification->id, 'interviewCandidate' => '/admin/candidates/'.$interviewCandidate->id,
        'prequal' => URL::temporarySignedRoute('prequalification.show', $expires, ['candidate' => $prequalification, 'token' => $token]),
        'interview' => URL::temporarySignedRoute('interview.booking.show', $expires, ['interview' => $interview, 'token' => $token]),
        'documents' => URL::temporarySignedRoute('contracting.documents.show', $expires, ['candidate' => $waiting, 'token' => $token]),
        'booking' => URL::temporarySignedRoute('contracting.booking.show', $expires, ['invitation' => $booking, 'token' => $token]),
    ],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
