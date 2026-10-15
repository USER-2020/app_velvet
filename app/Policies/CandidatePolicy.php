<?php

namespace App\Policies;

use App\Enums\CandidateType;
use App\Models\Candidate;
use App\Models\User;

class CandidatePolicy
{
    public function viewAssignedModel(User $user, Candidate $candidate): bool
    {
        if ($candidate->candidate_type !== CandidateType::MODEL) {
            return false;
        }

        if ($user->hasRole('super_admin') || $user->hasPermissionTo('models.view_all') || $user->hasPermissionTo('models.view_team')) {
            return true;
        }

        $monitorCandidateId = Candidate::query()->where('user_id', $user->id)->value('id');

        return $user->hasRole('monitor')
            && $monitorCandidateId
            && $candidate->modelAssignments()
                ->where('monitor_candidate_id', $monitorCandidateId)
                ->whereNull('ended_at')
                ->exists();
    }
}
