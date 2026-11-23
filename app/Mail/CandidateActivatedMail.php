<?php

namespace App\Mail;

use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CandidateActivatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Candidate $candidate)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '¡Felicitaciones! Tu perfil ya está activo · The Velvet Studio');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.candidate-activated', with: [
            'applicantName' => $this->candidate->lead->full_name,
            'candidateCode' => $this->candidate->code,
            'dashboardUrl' => route('dashboard'),
        ]);
    }
}
