<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Mail\RoomReservationScheduledMail;
use App\Models\Lead;
use App\Models\ModelMonitorAssignment;
use App\Models\OperationalShift;
use App\Models\RoomOperationChecklist;
use App\Models\Room;
use App\Models\RoomReservation;
use App\Models\ShiftAttendance;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VelvetMasterOperationalPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StudioOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VelvetMasterOperationalPermissionsSeeder::class);
    }

    public function test_manager_can_program_shift_with_room_reservation_and_checklists(): void
    {
        $manager = User::factory()->create()->assignRole('manager');
        $model = $this->candidate('MODEL');
        $monitor = $this->candidate('MONITOR');
        ModelMonitorAssignment::create(['model_candidate_id' => $model->id, 'monitor_candidate_id' => $monitor->id, 'assigned_at' => now(), 'assigned_by' => $manager->id]);
        $room = Room::create(['code' => 'ROOM-01', 'name' => 'Sala principal', 'status' => 'AVAILABLE', 'is_active' => true, 'created_by' => $manager->id]);
        $start = Carbon::now('America/Bogota')->addDay()->setTime(10, 0);

        $this->actingAs($manager)->post('/admin/operations/shifts', [
            'model_candidate_id' => $model->id,
            'monitor_candidate_id' => $monitor->id,
            'room_id' => $room->id,
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $start->copy()->addHours(4)->format('Y-m-d H:i:s'),
            'notes' => 'Turno de prueba.',
        ])->assertRedirect();

        $shift = OperationalShift::firstOrFail();
        $this->assertDatabaseHas('shift_attendances', ['operational_shift_id' => $shift->id, 'status' => 'PENDING']);
        $this->assertDatabaseHas('room_reservations', ['id' => $shift->room_reservation_id, 'room_id' => $room->id, 'candidate_id' => $model->id]);
        $this->assertDatabaseCount('room_operation_checklists', 2);

        $checklist = RoomOperationChecklist::firstOrFail();
        $this->actingAs($manager)->patch("/admin/operations/checklists/{$checklist->id}/complete", [
            'items' => collect($checklist->items)->map(fn ($item) => [...$item, 'completed' => true])->all(),
        ])->assertRedirect();
        $this->assertDatabaseHas('room_operation_checklists', ['id' => $checklist->id, 'status' => 'COMPLETED']);
    }

    public function test_shift_prevents_model_overlap_and_tracks_attendance_separately(): void
    {
        $manager = User::factory()->create()->assignRole('manager');
        $model = $this->candidate('MODEL');
        $start = Carbon::now('America/Bogota')->subMinutes(10);
        $shift = OperationalShift::create(['code' => 'SHIFT-ATTENDANCE', 'model_candidate_id' => $model->id, 'starts_at' => $start->copy()->utc(), 'ends_at' => $start->copy()->addHours(3)->utc(), 'status' => 'SCHEDULED', 'created_by' => $manager->id]);

        $this->actingAs($manager)->post('/admin/operations/shifts', [
            'model_candidate_id' => $model->id,
            'starts_at' => $start->copy()->addHour()->format('Y-m-d H:i:s'),
            'ends_at' => $start->copy()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('model_candidate_id');

        $this->actingAs($manager)->patch("/admin/operations/shifts/{$shift->id}/attendance", ['action' => 'CHECK_IN'])->assertRedirect();
        $this->actingAs($manager)->patch("/admin/operations/shifts/{$shift->id}/attendance", ['action' => 'CHECK_OUT'])->assertRedirect();

        $attendance = ShiftAttendance::where('operational_shift_id', $shift->id)->firstOrFail();
        $this->assertNotNull($attendance->checked_in_at);
        $this->assertNotNull($attendance->checked_out_at);
        $this->assertDatabaseHas('operational_shifts', ['id' => $shift->id, 'status' => 'COMPLETED']);
    }

    public function test_monitor_start_and_finish_turn_updates_room_card_and_operational_session(): void
    {
        $monitorUser = User::factory()->create(['is_active' => true])->assignRole('monitor');
        $monitor = $this->candidate('MONITOR');
        $monitor->update(['user_id' => $monitorUser->id]);
        $model = $this->candidate('MODEL');
        ModelMonitorAssignment::create(['model_candidate_id' => $model->id, 'monitor_candidate_id' => $monitor->id, 'assigned_at' => now()]);
        $room = Room::create(['code' => 'ROOM-START', 'name' => 'Sala de inicio', 'status' => 'AVAILABLE', 'is_active' => true, 'created_by' => $monitorUser->id]);
        $reservation = RoomReservation::create([
            'room_id' => $room->id,
            'candidate_id' => $model->id,
            'reserved_by' => $monitorUser->id,
            'purpose' => 'Turno de prueba',
            'starts_at' => Carbon::now('America/Bogota')->subMinute()->utc(),
            'ends_at' => Carbon::now('America/Bogota')->addHour()->utc(),
            'status' => 'SCHEDULED',
        ]);
        $shift = OperationalShift::create([
            'code' => 'SHIFT-ROOM-START',
            'model_candidate_id' => $model->id,
            'monitor_candidate_id' => $monitor->id,
            'room_reservation_id' => $reservation->id,
            'starts_at' => $reservation->starts_at,
            'ends_at' => $reservation->ends_at,
            'status' => 'SCHEDULED',
            'created_by' => $monitorUser->id,
        ]);

        $this->actingAs($monitorUser)
            ->patch("/monitor/rooms/reservations/{$reservation->id}/status", ['status' => 'IN_USE'])
            ->assertRedirect();

        $this->assertDatabaseHas('room_reservations', ['id' => $reservation->id, 'status' => 'IN_USE']);
        $this->assertDatabaseHas('operational_shifts', ['id' => $shift->id, 'status' => 'IN_PROGRESS']);
        $this->assertDatabaseHas('shift_attendances', ['operational_shift_id' => $shift->id, 'status' => 'ON_TIME']);
        $this->assertDatabaseHas('operational_sessions', ['operational_shift_id' => $shift->id, 'room_reservation_id' => $reservation->id, 'candidate_id' => $model->id, 'status' => 'OPEN']);

        $this->actingAs($monitorUser)
            ->patch("/monitor/rooms/reservations/{$reservation->id}/status", ['status' => 'COMPLETED'])
            ->assertRedirect();

        $this->assertDatabaseHas('room_reservations', ['id' => $reservation->id, 'status' => 'COMPLETED']);
        $this->assertDatabaseHas('operational_shifts', ['id' => $shift->id, 'status' => 'COMPLETED']);
        $this->assertDatabaseHas('shift_attendances', ['operational_shift_id' => $shift->id, 'status' => 'COMPLETED']);
        $this->assertDatabaseHas('operational_sessions', ['room_reservation_id' => $reservation->id, 'status' => 'COMPLETED']);
        $this->assertNotNull(DB::table('operational_sessions')->where('room_reservation_id', $reservation->id)->value('ended_at'));
    }

    public function test_monitor_reservation_keeps_the_selected_bogota_date_and_time(): void
    {
        $monitorUser = User::factory()->create(['is_active' => true])->assignRole('monitor');
        $monitor = $this->candidate('MONITOR');
        $monitor->update(['user_id' => $monitorUser->id]);
        $model = $this->candidate('MODEL');
        ModelMonitorAssignment::create(['model_candidate_id' => $model->id, 'monitor_candidate_id' => $monitor->id, 'assigned_at' => now()]);
        $room = Room::create(['code' => 'ROOM-DATETIME', 'name' => 'Sala de fechas', 'status' => 'AVAILABLE', 'is_active' => true, 'created_by' => $monitorUser->id]);
        $selectedStart = Carbon::create(2026, 12, 15, 10, 30, 0, 'America/Bogota');
        $selectedEnd = $selectedStart->copy()->addHours(2);

        $this->actingAs($monitorUser)->post('/monitor/rooms/reservations', [
            'room_id' => $room->id,
            'candidate_id' => $model->id,
            'purpose' => 'Sesión de estudio',
            'starts_at' => $selectedStart->format('Y-m-d\\TH:i'),
            'ends_at' => $selectedEnd->format('Y-m-d\\TH:i'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $reservation = RoomReservation::query()->latest('id')->firstOrFail();
        $this->assertSame($selectedStart->utc()->format('Y-m-d H:i:s'), $reservation->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($selectedEnd->utc()->format('Y-m-d H:i:s'), $reservation->ends_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('10:30', $reservation->starts_at->timezone('America/Bogota')->format('H:i'));
        $this->assertSame('12:30', $reservation->ends_at->timezone('America/Bogota')->format('H:i'));
    }

    public function test_monitor_can_schedule_a_batch_without_room_overlap_and_notifies_the_model(): void
    {
        Mail::fake();
        $monitorUser = User::factory()->create(['is_active' => true])->assignRole('monitor');
        $monitor = $this->candidate('MONITOR');
        $monitor->update(['user_id' => $monitorUser->id]);
        $modelUser = User::factory()->create(['is_active' => true])->assignRole('model');
        $model = $this->candidate('MODEL');
        $model->update(['user_id' => $modelUser->id]);
        $otherModel = $this->candidate('MODEL');
        ModelMonitorAssignment::create(['model_candidate_id' => $model->id, 'monitor_candidate_id' => $monitor->id, 'assigned_at' => now()]);
        ModelMonitorAssignment::create(['model_candidate_id' => $otherModel->id, 'monitor_candidate_id' => $monitor->id, 'assigned_at' => now()]);
        $room = Room::create(['code' => 'ROOM-BATCH', 'name' => 'Sala de lotes', 'status' => 'AVAILABLE', 'is_active' => true, 'created_by' => $monitorUser->id]);
        $firstStart = Carbon::now('America/Bogota')->addDays(2)->setTime(9, 0);
        $secondStart = $firstStart->copy()->addHours(4);
        $payload = fn (Carbon $start) => ['starts_at' => $start->format('Y-m-d\\TH:i'), 'ends_at' => $start->copy()->addHours(2)->format('Y-m-d\\TH:i')];

        $this->actingAs($monitorUser)->post('/monitor/rooms/reservations', [
            'room_id' => $room->id,
            'candidate_id' => $model->id,
            'purpose' => 'Sesión por lotes',
            'slots' => [$payload($firstStart), $payload($secondStart)],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, RoomReservation::where('room_id', $room->id)->count());
        $this->assertSame(2, $model->activities()->where('type', 'room_reservation_scheduled')->count());
        $this->assertSame(2, $modelUser->notifications()->where('type', 'room.reservation.created')->count());
        Mail::assertQueued(RoomReservationScheduledMail::class, 2);

        $this->actingAs($monitorUser)->post('/monitor/rooms/reservations', [
            'room_id' => $room->id,
            'candidate_id' => $otherModel->id,
            'purpose' => 'Sesión cruzada',
            'slots' => [$payload($firstStart->copy()->addMinutes(30))],
        ])->assertSessionHasErrors('slots.0.starts_at');

        $this->assertSame(2, RoomReservation::where('room_id', $room->id)->count());
        $this->assertSame(2, $model->activities()->where('type', 'room_reservation_scheduled')->count());
    }

    public function test_active_model_can_register_operational_request(): void
    {
        $user = User::factory()->create()->assignRole('model');
        $candidate = $this->candidate('MODEL');
        $candidate->update(['user_id' => $user->id]);

        $this->actingAs($user)->post('/portal/requests', [
            'type' => 'PERMISO',
            'starts_at' => Carbon::now('America/Bogota')->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => Carbon::now('America/Bogota')->addDays(2)->format('Y-m-d H:i:s'),
            'details' => 'Solicitud de permiso personal.',
        ])->assertRedirect();

        $this->assertDatabaseHas('operational_requests', ['candidate_id' => $candidate->id, 'requested_by' => $user->id, 'status' => 'PENDING']);
    }

    private function candidate(string $type): Candidate
    {
        $id = fake()->unique()->numerify('#####');
        $lead = Lead::create(['code' => "LEAD-{$id}", 'status' => 'CONVERTED', 'candidate_type' => $type, 'first_name' => 'Operativa', 'last_name' => $id, 'sex' => 'WOMAN', 'phone' => "300{$id}", 'email' => "operativa-{$id}@example.test", 'country' => 'Colombia', 'city' => 'Bogota', 'birth_date' => '1998-01-01', 'availability' => 'Tiempo completo', 'work_mode' => 'En estudio']);
        return Candidate::create(['code' => "CAN-{$id}", 'lead_id' => $lead->id, 'candidate_type' => $type, 'status' => 'ACTIVE']);
    }
}
