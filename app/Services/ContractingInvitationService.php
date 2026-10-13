<?php

namespace App\Services;

use App\Enums\CandidateStatus;
use App\Mail\ContractingInvitationMail;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\ContractInvitation;
use App\Models\ContractSlot;
use App\Models\LeadDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ContractingInvitationService
{
    public function send(Candidate $candidate, ?User $user = null): ?ContractInvitation
    {
        $candidate->loadMissing('lead');
        if ($candidate->status !== CandidateStatus::WAITING || ! $this->hasVerifiedRequiredDocuments($candidate) || ! $this->hasAvailableSlots()) return null;
        if ($candidate->contractAppointments()->where('status', 'SCHEDULED')->exists()) return null;
        if ($candidate->contractInvitations()->where('status', 'INVITED')->where('invitation_expires_at', '>', now())->exists()) return null;

        $token = str()->random(64);
        $expiresAt = now()->addDays(7);
        $invitation = DB::transaction(function () use ($candidate, $user, $token, $expiresAt): ContractInvitation {
            $candidate->contractInvitations()->where('status', 'INVITED')->update(['status' => 'EXPIRED']);
            $invitation = ContractInvitation::create([
                'candidate_id' => $candidate->id,
                'created_by' => $user?->id,
                'token_hash' => hash('sha256', $token),
                'invitation_expires_at' => $expiresAt,
                'status' => 'INVITED',
            ]);
            CandidateActivity::create([
                'candidate_id' => $candidate->id,
                'user_id' => $user?->id,
                'type' => 'contracting_invited',
                'description' => 'Se enviaron horarios disponibles para agendar la contratación.',
                'metadata' => ['expires_at' => $expiresAt->toIso8601String(), 'invitation_id' => $invitation->id],
            ]);
            return $invitation;
        });

        $url = URL::temporarySignedRoute('contracting.booking.show', $expiresAt, ['invitation' => $invitation->id, 'token' => $token]);

        try {
            Mail::to($candidate->lead->email)->send(new ContractingInvitationMail($candidate, $invitation, $url));
        } catch (\Throwable $exception) {
            // Do not leave a failed delivery blocking the automatic retry.
            $invitation->update(['status' => 'FAILED']);

            throw $exception;
        }

        return $invitation;
    }

    public function hasAvailableSlots(): bool
    {
        return ContractSlot::where('status', 'AVAILABLE')->where('starts_at', '>', now('UTC'))->exists();
    }

    public function hasVerifiedRequiredDocuments(Candidate $candidate): bool
    {
        $candidate->loadMissing('lead.documents');
        $required = ['identity_document', 'bank_certificate', 'tax_document'];
        $latestByRequirement = $candidate->lead->documents
            ->where('type', 'contract_document')
            ->filter(fn (LeadDocument $document) => filled(data_get($document->metadata, 'requirement_key')))
            ->sortByDesc('id')
            ->unique(fn (LeadDocument $document) => data_get($document->metadata, 'requirement_key'))
            ->keyBy(fn (LeadDocument $document) => data_get($document->metadata, 'requirement_key'));

        return collect($required)->every(fn (string $key) => $latestByRequirement->get($key)?->status === 'VERIFIED');
    }
}
