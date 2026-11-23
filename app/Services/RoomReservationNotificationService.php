<?php

namespace App\Services;

use App\Mail\RoomReservationScheduledMail;
use App\Models\CandidateActivity;
use App\Models\RoomReservation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class RoomReservationNotificationService
{
    public function notify(User $actor, RoomReservation $reservation): void
    {
        $reservation->loadMissing(['room', 'candidate.lead', 'candidate.user']);
        $candidate = $reservation->candidate;
        if (! $candidate) return;

        $start = $reservation->starts_at?->copy()->timezone('America/Bogota');
        $roomName = $reservation->room?->name ?: $reservation->room?->code ?: 'la room';
        $schedule = $start ? $start->locale('es')->translatedFormat('l d \\d\\e F \\d\\e Y, H:i') : 'la fecha programada';
        $description = "Se programó {$roomName} para {$schedule} (Bogotá).";

        CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $actor->id,
            'type' => 'room_reservation_scheduled',
            'description' => $description,
            'metadata' => [
                'room_reservation_id' => $reservation->id,
                'room_id' => $reservation->room_id,
                'starts_at' => $reservation->starts_at?->toIso8601String(),
                'ends_at' => $reservation->ends_at?->toIso8601String(),
            ],
        ]);

        $payload = [
            'room_id' => $reservation->room_id,
            'reservation_id' => $reservation->id,
            'room_reservation_id' => $reservation->id,
            'candidate_id' => $candidate->id,
            'starts_at' => $reservation->starts_at?->toIso8601String(),
            'ends_at' => $reservation->ends_at?->toIso8601String(),
            'portal_path' => '/model/agenda',
            'notification' => ['title' => 'Nueva reserva de room', 'description' => $description],
        ];
        if ($candidate->user_id) $payload['notification_user_ids'] = [$candidate->user_id];
        app(RealtimePublisher::class)->publish('room.reservation.created', $payload);

        $email = $candidate->user?->email ?: $candidate->lead?->email;
        if ($email) Mail::to($email)->queue(new RoomReservationScheduledMail($reservation));
    }
}
