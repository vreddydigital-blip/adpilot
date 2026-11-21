<?php

namespace App\Policies;

use App\Models\AnalysisJob;
use App\Models\User;

class AnalysisJobPolicy
{
    public function view(User $user, AnalysisJob $analysisJob): bool
    {
        return $user->id === $analysisJob->user_id
            && $user->id === $analysisJob->website->user_id;
    }
}
