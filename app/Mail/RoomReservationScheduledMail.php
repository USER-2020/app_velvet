<?php

namespace App\Mail;

use App\Models\RoomReservation;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RoomReservationScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RoomReservation $reservation)
    {
        $this->reservation->loadMissing(['room', 'candidate.lead']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu room ha sido programada · The Velvet Studio');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.room-reservation-scheduled', with: [
            'modelName' => $this->reservation->candidate?->lead?->full_name ?: 'Hola',
            'roomName' => $this->reservation->room?->name ?: $this->reservation->room?->code,
            'roomCode' => $this->reservation->room?->code,
            'purpose' => $this->reservation->purpose,
            'startsAt' => $this->reservation->starts_at->copy()->timezone('America/Bogota')->locale('es'),
            'endsAt' => $this->reservation->ends_at->copy()->timezone('America/Bogota')->locale('es'),
        ]);
    }

    public function attachments(): array
    {
        $start = $this->reservation->starts_at->copy()->utc();
        $end = $this->reservation->ends_at->copy()->utc();
        $roomName = $this->reservation->room?->name ?: $this->reservation->room?->code ?: 'Room';
        $uid = 'room-reservation-' . $this->reservation->id . '@thevelvetstudio.co';
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//The Velvet Studio//Rooms//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:' . $start->format('Ymd\THis\Z'),
            'DTEND:' . $end->format('Ymd\THis\Z'),
            'SUMMARY:' . $this->escapeIcs($roomName . ' · The Velvet Studio'),
            'DESCRIPTION:' . $this->escapeIcs($this->reservation->purpose ?: 'Reserva de room'),
            'LOCATION:' . $this->escapeIcs($roomName),
            'BEGIN:VALARM',
            'TRIGGER:-PT30M',
            'ACTION:DISPLAY',
            'DESCRIPTION:Tu reserva de room comienza en 30 minutos.',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        return [Attachment::fromData(fn () => $ics, 'room-' . $this->reservation->id . '-velvet.ics')->withMime('text/calendar')];
    }

    private function escapeIcs(?string $value): string
    {
        return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\\;", "\\,", '\\n', '\\n', '\\n'], $value ?: '');
    }
}
