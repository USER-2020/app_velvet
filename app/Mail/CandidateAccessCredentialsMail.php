<?php

namespace App\Mail;

use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class CandidateAccessCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Candidate $candidate, public string $temporaryPassword)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tus credenciales de acceso · The Velvet Studio');
    }

    public function content(): Content
    {
        $user = $this->candidate->user;
        $verificationUrl = $user
            ? URL::temporarySignedRoute('verification.verify', now()->addMinutes(config('auth.verification.expire', 60)), [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ])
            : route('login');

        return new Content(view: 'emails.candidate-access-credentials', with: [
            'applicantName' => $this->candidate->lead->full_name,
            'candidateCode' => $this->candidate->code,
            'email' => $this->candidate->lead->email,
            'temporaryPassword' => $this->temporaryPassword,
            'verificationUrl' => $verificationUrl,
            'loginUrl' => route('login'),
        ]);
    }
}
