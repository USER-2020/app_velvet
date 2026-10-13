<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\ModelMonitorAssignment;
use App\Models\OperationalIncident;
use App\Models\OperationalNote;
use App\Models\OperationalProfile;
use App\Models\OperationalRequest;
use App\Models\OperationalShift;
use App\Models\OperationalTrainingRecord;
use App\Models\Room;
use App\Models\RoomOperationChecklist;
use App\Models\RoomReservation;
use App\Models\RoomUsageLog;
use App\Models\ShiftAttendance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostracion aislados para probar la operacion diaria del estudio.
 *
 * Todos los registros usan codigos TEST- y correos @velvet.test. Este seeder no
 * forma parte de DatabaseSeeder: ejecútalo solamente en desarrollo o staging.
 */
class TestOperationalModelsAndMonitorsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TestUsersSeeder::class);

        $admin = User::query()->where('email', 'administrador@velvet.test')->firstOrFail();
        $password = env('VELVET_TEST_USERS_PASSWORD', 'ChangeMe123!');
        $now = now('America/Bogota');

        $portalUsers = [
            'monitor_carlos' => $this->testUser('monitor@velvet.test', 'Carlos Monitor de Prueba', 'monitor', $password),
            'monitor_martin' => $this->testUser('monitor.martin@velvet.test', 'Martin Monitor de Prueba', 'monitor', $password),
            'model_laura' => $this->testUser('modelo@velvet.test', 'Laura Modelo de Prueba', 'model', $password),
            'model_sofia' => $this->testUser('modelo.sofia@velvet.test', 'Sofia Modelo de Prueba', 'model', $password),
            'model_valeria' => $this->testUser('modelo.valeria@velvet.test', 'Valeria Modelo de Prueba', 'model', $password),
        ];

        $candidates = [
            'monitor_carlos' => $this->candidate('TEST-MON-001', 'MONITOR', 'Carlos', 'Rojas', 'monitor.carlos@velvet.test', '3000001001', $portalUsers['monitor_carlos']->id, $admin->id),
            'monitor_martin' => $this->candidate('TEST-MON-002', 'MONITOR', 'Martin', 'Londoño', 'monitor.martin@velvet.test', '3000001002', $portalUsers['monitor_martin']->id, $admin->id),
            'model_laura' => $this->candidate('TEST-MOD-001', 'MODEL', 'Laura', 'Gomez', 'modelo.laura@velvet.test', '3000002001', $portalUsers['model_laura']->id, $admin->id),
            'model_sofia' => $this->candidate('TEST-MOD-002', 'MODEL', 'Sofia', 'Martinez', 'modelo.sofia@velvet.test', '3000002002', $portalUsers['model_sofia']->id, $admin->id),
            'model_valeria' => $this->candidate('TEST-MOD-003', 'MODEL', 'Valeria', 'Torres', 'modelo.valeria@velvet.test', '3000002003', $portalUsers['model_valeria']->id, $admin->id),
        ];

        foreach ($candidates as $key => $candidate) {
            $isModel = str_starts_with($key, 'model_');
            OperationalProfile::updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'profile_type' => $isModel ? 'MODEL' : 'MONITOR',
                    'operational_status' => 'ACTIVE',
                    'onboarding_completed_at' => $now->copy()->subMonths(3),
                    'induction_completed_at' => $now->copy()->subMonths(2),
                    'activated_at' => $now->copy()->subMonths(2),
                    'activated_by' => $admin->id,
                    'notes' => 'Perfil operativo de demostracion. No corresponde a una persona real.',
                    'metadata' => ['seed' => 'TestOperationalModelsAndMonitorsSeeder', 'environment' => 'test-data'],
                ],
            );

            foreach ([
                ['kind' => 'ONBOARDING', 'title' => 'Induccion operativa y protocolos internos', 'status' => 'COMPLETED', 'required' => true, 'completed_at' => $now->copy()->subMonths(2)],
                ['kind' => 'TRAINING', 'title' => 'Seguridad digital y manejo de incidencias', 'status' => $isModel ? 'IN_PROGRESS' : 'COMPLETED', 'required' => true, 'completed_at' => $isModel ? null : $now->copy()->subMonth()],
            ] as $training) {
                OperationalTrainingRecord::updateOrCreate(
                    ['candidate_id' => $candidate->id, 'title' => $training['title']],
                    [
                        'kind' => $training['kind'], 'status' => $training['status'], 'is_required' => $training['required'],
                        'due_at' => $now->copy()->addWeeks(2), 'completed_at' => $training['completed_at'],
                        'completed_by' => $training['completed_at'] ? $admin->id : null, 'created_by' => $admin->id,
                        'notes' => 'Registro generado para pruebas funcionales.', 'metadata' => ['seed' => 'test-operational'],
                    ],
                );
            }
        }

        // Sofia conserva un cambio de monitor cerrado y uno vigente para probar el historial.
        $this->assignment($candidates['model_sofia'], $candidates['monitor_martin'], $admin->id, $now->copy()->subMonths(2), $now->copy()->subMonth(), 'Reasignacion de prueba por ajuste de cobertura.');
        $this->assignment($candidates['model_sofia'], $candidates['monitor_carlos'], $admin->id, $now->copy()->subMonth(), null, 'Asignacion vigente de demostracion.');
        $this->assignment($candidates['model_laura'], $candidates['monitor_carlos'], $admin->id, $now->copy()->subWeeks(3), null, 'Asignacion vigente de demostracion.');
        $this->assignment($candidates['model_valeria'], $candidates['monitor_martin'], $admin->id, $now->copy()->subWeeks(2), null, 'Asignacion vigente de demostracion.');

        $room = Room::updateOrCreate(
            ['code' => 'TEST-OPS-01'],
            ['name' => 'Sala operativa de prueba', 'description' => 'Sala simulada para turnos, asistencia y checklists.', 'status' => 'AVAILABLE', 'is_active' => true],
        );

        $completedShift = $this->shift('TEST-SHIFT-001', $candidates['model_laura'], $candidates['monitor_carlos'], $admin->id, $now->copy()->subDay()->setTime(9, 0), $now->copy()->subDay()->setTime(15, 0), 'COMPLETED');
        $scheduledShift = $this->shift('TEST-SHIFT-002', $candidates['model_sofia'], $candidates['monitor_carlos'], $admin->id, $now->copy()->addDay()->setTime(10, 0), $now->copy()->addDay()->setTime(16, 0), 'SCHEDULED');

        $reservation = RoomReservation::updateOrCreate(
            ['room_id' => $room->id, 'purpose' => 'Turno TEST-SHIFT-001'],
            [
                'candidate_id' => $candidates['model_laura']->id, 'user_id' => $portalUsers['model_laura']->id,
                'reserved_by' => $admin->id, 'starts_at' => $completedShift->starts_at, 'ends_at' => $completedShift->ends_at,
                'status' => 'COMPLETED', 'checked_in_at' => $completedShift->starts_at, 'started_at' => $completedShift->starts_at,
                'ended_at' => $completedShift->ends_at, 'notes' => 'Reserva simulada vinculada al turno de prueba.',
            ],
        );
        $completedShift->update(['room_reservation_id' => $reservation->id]);
        RoomUsageLog::updateOrCreate(
            ['room_reservation_id' => $reservation->id, 'to_status' => 'AVAILABLE'],
            ['room_id' => $room->id, 'changed_by' => $admin->id, 'from_status' => 'IN_USE', 'notes' => 'Cierre simulado del turno de prueba.'],
        );
        foreach (['OPENING', 'CLOSING'] as $kind) {
            RoomOperationChecklist::updateOrCreate(
                ['room_id' => $room->id, 'operational_shift_id' => $completedShift->id, 'kind' => $kind],
                ['status' => 'COMPLETED', 'items' => $this->checklist($kind, true), 'notes' => 'Checklist de demostracion completado.', 'completed_at' => $completedShift->ends_at, 'completed_by' => $admin->id],
            );
        }
        RoomOperationChecklist::updateOrCreate(
            ['room_id' => $room->id, 'operational_shift_id' => $scheduledShift->id, 'kind' => 'OPENING'],
            ['status' => 'PENDING', 'items' => $this->checklist('OPENING', false), 'notes' => 'Checklist pendiente para el turno programado.'],
        );

        ShiftAttendance::updateOrCreate(
            ['operational_shift_id' => $completedShift->id],
            ['status' => 'COMPLETED', 'checked_in_at' => $completedShift->starts_at->copy()->addMinutes(5), 'checked_out_at' => $completedShift->ends_at, 'break_minutes' => 30, 'late_minutes' => 5, 'notes' => 'Asistencia de demostracion con retraso registrado.', 'updated_by' => $admin->id],
        );
        ShiftAttendance::updateOrCreate(['operational_shift_id' => $scheduledShift->id], ['status' => 'PENDING', 'break_minutes' => 0, 'late_minutes' => 0, 'updated_by' => $admin->id]);

        $incident = OperationalIncident::updateOrCreate(
            ['candidate_id' => $candidates['model_laura']->id, 'category' => 'EQUIPMENT', 'description' => 'Incidencia simulada: revision preventiva de iluminacion requerida.'],
            ['operational_shift_id' => $completedShift->id, 'room_id' => $room->id, 'reported_by' => $admin->id, 'severity' => 'LOW', 'status' => 'IN_PROGRESS'],
        );
        OperationalRequest::updateOrCreate(
            ['candidate_id' => $candidates['model_valeria']->id, 'type' => 'SCHEDULE_CHANGE', 'details' => 'Solicitud simulada de ajuste de horario para la proxima semana.'],
            ['requested_by' => $portalUsers['model_valeria']->id, 'status' => 'PENDING', 'starts_at' => $now->copy()->addDays(3)->setTime(14, 0), 'ends_at' => $now->copy()->addDays(3)->setTime(20, 0)],
        );
        OperationalNote::updateOrCreate(
            ['candidate_id' => $candidates['model_laura']->id, 'body' => 'Seguimiento simulado: se confirma acompañamiento del monitor y revisión del equipo.'],
            ['operational_shift_id' => $completedShift->id, 'operational_incident_id' => $incident->id, 'author_id' => $admin->id],
        );
        CandidateActivity::firstOrCreate(
            ['candidate_id' => $candidates['model_laura']->id, 'type' => 'test_operational_data_seeded'],
            ['user_id' => $admin->id, 'description' => 'Datos operativos de demostracion creados.', 'metadata' => ['seed' => 'TestOperationalModelsAndMonitorsSeeder']],
        );

        $this->command?->info('Datos de prueba operativos creados o actualizados: 3 modelos, 2 monitores y sus flujos relacionados.');
    }

    private function testUser(string $email, string $name, string $role, string $password): User
    {
        $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make($password), 'is_active' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$role]);

        return $user;
    }

    private function candidate(string $code, string $type, string $firstName, string $lastName, string $email, string $phone, int $userId, int $adminId): Candidate
    {
        $lead = \App\Models\Lead::updateOrCreate(
            ['code' => "LEAD-{$code}"],
            [
                'candidate_type' => $type, 'first_name' => $firstName, 'last_name' => $lastName, 'phone' => $phone, 'email' => $email,
                'country' => 'Colombia', 'city' => 'Bogota', 'birth_date' => '1998-06-15', 'experience' => 'Perfil de demostracion para validar flujos operativos.',
                'availability' => 'Tiempo completo', 'work_mode' => 'En estudio', 'source' => 'TEST_SEEDER', 'status' => 'CONVERTED', 'assigned_to' => $adminId,
            ],
        );

        return Candidate::updateOrCreate(
            ['code' => $code],
            ['lead_id' => $lead->id, 'user_id' => $userId, 'candidate_type' => $type, 'status' => 'ACTIVE', 'assigned_recruiter_id' => $adminId, 'admitted_at' => now()->subMonths(3), 'contracted_at' => now()->subMonths(2), 'activated_at' => now()->subMonths(2)],
        );
    }

    private function assignment(Candidate $model, Candidate $monitor, int $adminId, Carbon $assignedAt, ?Carbon $endedAt, string $reason): void
    {
        ModelMonitorAssignment::updateOrCreate(
            ['model_candidate_id' => $model->id, 'monitor_candidate_id' => $monitor->id, 'reason' => $reason],
            ['assigned_by' => $adminId, 'assigned_at' => $assignedAt, 'ended_at' => $endedAt, 'ended_by' => $endedAt ? $adminId : null],
        );
    }

    private function shift(string $code, Candidate $model, Candidate $monitor, int $adminId, Carbon $startsAt, Carbon $endsAt, string $status): OperationalShift
    {
        return OperationalShift::updateOrCreate(
            ['code' => $code],
            ['model_candidate_id' => $model->id, 'monitor_candidate_id' => $monitor->id, 'starts_at' => $startsAt->utc(), 'ends_at' => $endsAt->utc(), 'status' => $status, 'notes' => 'Turno de demostracion generado por el seeder.', 'created_by' => $adminId],
        );
    }

    private function checklist(string $kind, bool $completed): array
    {
        $items = $kind === 'OPENING'
            ? ['Verificar limpieza y equipos', 'Confirmar iluminacion y conectividad', 'Registrar condiciones iniciales']
            : ['Verificar estado final de equipos', 'Registrar orden y limpieza', 'Reportar novedades de sala'];

        return collect($items)->map(fn (string $label) => ['label' => $label, 'completed' => $completed])->all();
    }
}
