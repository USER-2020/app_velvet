<?php

namespace App\Mail;

use App\Models\ContractAppointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractingScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContractAppointment $appointment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu contratación ha sido programada · The Velvet Studio');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contracting-scheduled', with: [
            'applicantName' => $this->appointment->candidate->lead->full_name,
            'candidateCode' => $this->appointment->candidate->code,
            'startsAt' => $this->slotDate('starts_at')->setTimezone('America/Bogota')->locale('es'),
            'endsAt' => $this->slotDate('ends_at')->setTimezone('America/Bogota')->locale('es'),
        ]);
    }

    public function attachments(): array
    {
        $start = $this->slotDate('starts_at');
        $end = $this->slotDate('ends_at');
        $name = $this->appointment->candidate->lead->full_name;
        $uid = 'contracting-' . $this->appointment->id . '@thevelvetstudio.co';
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//The Velvet Studio//Contratacion//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:' . $start->format('Ymd\THis\Z'),
            'DTEND:' . $end->format('Ymd\THis\Z'),
            'SUMMARY:Contratacion · The Velvet Studio',
            'DESCRIPTION:Cita de contratacion para ' . $name . ' · Codigo de candidato: ' . $this->appointment->candidate->code,
            'LOCATION:The Velvet Studio',
            'BEGIN:VALARM',
            'TRIGGER:-PT30M',
            'ACTION:DISPLAY',
            'DESCRIPTION:Tu cita de contratacion con The Velvet Studio comienza en 30 minutos.',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        return [Attachment::fromData(fn () => $ics, 'contratacion-velvet.ics')->withMime('text/calendar')];
    }

    private function slotDate(string $field): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $this->appointment->slot->getRawOriginal($field), 'UTC');
    }
}
