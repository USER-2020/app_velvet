<?php

namespace App\Services;

use App\Enums\CandidateStatus;
use App\Mail\PrequalificationFormMail;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\InterviewSlot;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CandidateWorkflowAutomationService
{
    public function __construct(private readonly InterviewInvitationService $interviewInvitations)
    {
    }

    public function start(Candidate $candidate): void
    {
        $candidate->loadMissing('lead');

        if (! in_array($candidate->status, [CandidateStatus::NEW, CandidateStatus::CONTACTED], true)
            || $candidate->prequalification_completed_at
            || $candidate->prequalification_token_hash) {
            return;
        }

        if (! $candidate->lead?->email) {
            $this->pause($candidate, 'No se pudo enviar la precalificación porque el lead no tiene correo.');
            return;
        }

        $token = Str::random(64);
        $expiresAt = now()->addDays(7);
        $formUrl = URL::temporarySignedRoute('prequalification.show', $expiresAt, [
            'candidate' => $candidate->id,
            'token' => $token,
        ]);

        try {
            Mail::to($candidate->lead->email)->send(new PrequalificationFormMail($candidate, $formUrl));
        } catch (\Throwable $exception) {
            report($exception);
            $this->pause($candidate, 'El lead fue convertido, pero el correo de precalificación no pudo enviarse.');
            return;
        }

        $candidate->update([
            'status' => CandidateStatus::CONTACTED,
            'prequalification_token_hash' => hash('sha256', $token),
            'prequalification_sent_at' => now(),
            'prequalification_expires_at' => $expiresAt,
        ]);

        CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'type' => 'automation_prequalification_sent',
            'description' => 'Automatización: se envió la precalificación después de convertir el lead.',
            'metadata' => ['expires_at' => $expiresAt->toIso8601String(), 'automation' => true],
        ]);
    }

    public function continueAfterPrequalification(Candidate $candidate): void
    {
        $candidate->loadMissing('lead');

        if ($candidate->status !== CandidateStatus::PREQUALIFIED
            || $candidate->interviews()->whereIn('status', ['INVITED', 'SCHEDULED'])->exists()) {
            return;
        }

        if (! InterviewSlot::query()->where('status', 'AVAILABLE')->where('starts_at', '>', now('UTC'))->exists()) {
            $this->pause($candidate, 'La precalificación está completa, pero no hay franjas de entrevista disponibles.');
            return;
        }

        try {
            $interview = $this->interviewInvitations->send($candidate, null, true);
        } catch (\Throwable $exception) {
            report($exception);
            $this->pause($candidate, 'La precalificación está completa, pero no se pudo enviar la invitación de entrevista.');
            return;
        }

        if (! $interview) {
            $this->pause($candidate, 'La precalificación está completa y requiere generar franjas de entrevista.');
            return;
        }

        CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'type' => 'automation_interview_invited',
            'description' => 'Automatización: se enviaron horarios de entrevista después de completar la precalificación.',
            'metadata' => ['interview_id' => $interview->id, 'automation' => true],
        ]);
    }

    private function pause(Candidate $candidate, string $description): void
    {
        CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'type' => 'automation_paused',
            'description' => $description,
            'metadata' => ['automation' => true, 'requires_human_review' => true],
        ]);
    }
}
