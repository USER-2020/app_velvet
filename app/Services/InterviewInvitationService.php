<?php

namespace App\Services;

use App\Enums\CandidateStatus;
use App\Mail\InterviewInvitationMail;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\Interview;
use App\Models\InterviewSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class InterviewInvitationService
{
    /**
     * Creates an invitation, reserves no slot yet, and emails the candidate
     * the secure page where they can choose one of the available slots.
     */
    public function send(Candidate $candidate, User $user, bool $advanceCandidate = true): ?Interview
    {
        $candidate->loadMissing('lead');
        $slotsExist = InterviewSlot::where('status', 'AVAILABLE')
            ->where('starts_at', '>', now())
            ->exists();

        if (! $slotsExist) {
            return null;
        }

        $token = str()->random(64);
        $expiresAt = now()->addDays(7);
        $interview = DB::transaction(function () use ($candidate, $user, $token, $expiresAt, $advanceCandidate) {
            Interview::where('candidate_id', $candidate->id)
                ->whereIn('status', ['INVITED', 'SCHEDULED'])
                ->update(['status' => 'CANCELLED']);

            $interview = Interview::create([
                'candidate_id' => $candidate->id,
                'created_by' => $user->id,
                'status' => 'INVITED',
                'invitation_token_hash' => hash('sha256', $token),
                'invitation_expires_at' => $expiresAt,
            ]);

            if ($advanceCandidate && $candidate->status === CandidateStatus::PREQUALIFIED) {
                $candidate->update(['status' => CandidateStatus::INTERVIEW]);
            }

            $invitationActivity = CandidateActivity::create([
                'candidate_id' => $candidate->id,
                'user_id' => $user->id,
                'type' => 'interview_invited',
                'description' => 'Se enviaron horarios disponibles para agendar la entrevista.',
                'metadata' => ['expires_at' => $expiresAt->toIso8601String()],
            ]);

            // El controlador publica esta actividad junto al cambio de etapa
            // para que los historiales abiertos la reciban en tiempo real.
            $interview->setRelation('invitationActivity', $invitationActivity);

            return $interview;
        });

        $url = URL::temporarySignedRoute('interview.booking.show', $expiresAt, [
            'interview' => $interview->id,
            'token' => $token,
        ]);

        Mail::to($candidate->lead->email)->send(new InterviewInvitationMail($candidate, $interview, $url));

        return $interview;
    }
}
