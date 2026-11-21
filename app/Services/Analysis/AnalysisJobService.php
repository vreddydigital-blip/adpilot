<?php

namespace App\Services\Analysis;

use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Services\Crawler\GitHubActionsDispatcher;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnalysisJobService
{
    public function startAnalysis(Website $website, User $user, Request $request): array
    {
        $existingJob = AnalysisJob::where('website_id', $website->id)
            ->whereIn('status', AnalysisJob::ACTIVE_STATUSES)
            ->latest('id')
            ->first();

        if ($existingJob !== null) {
            return [
                'job' => $existingJob,
                'created' => false,
            ];
        }

        $job = AnalysisJob::create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'status' => AnalysisJob::STATUS_PENDING,
            'attempt' => 0,
        ]);

        AuditLog::record(
            'analysis_job.created',
            $user->id,
            $job,
            ['website_id' => $website->id, 'website_host' => $website->host],
            $request->ip(),
            $request->userAgent(),
        );

        $dispatch = app(GitHubActionsDispatcher::class)->dispatch($job);

        return [
            'job' => $job->refresh(),
            'created' => true,
            'dispatch' => $dispatch,
        ];
    }
}
