<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\LeadDocument;
use App\Models\User;

class LeadDocumentPolicy
{
    public function view(User $user, LeadDocument $document): bool
    {
        if ($user->hasPermissionTo('documents.view')) {
            return true;
        }

        return $user->hasPermissionTo('documents.view_own')
            && Candidate::query()
                ->where('user_id', $user->id)
                ->where('lead_id', $document->lead_id)
                ->exists();
    }
}
