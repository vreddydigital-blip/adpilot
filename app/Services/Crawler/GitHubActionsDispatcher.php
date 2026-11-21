<?php

namespace App\Services\Crawler;

use App\Models\AnalysisJob;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GitHubActionsDispatcher
{
    public function dispatch(AnalysisJob $job): array
    {
        $job->loadMissing('website');
        $token = (string) config('services.crawler.github_token');
        $repo = (string) config('services.crawler.github_repo');
        $workflow = (string) config('services.crawler.workflow', 'crawler.yml');
        $callbackBase = (string) config('services.crawler.callback_base_url');

        if ($token === '' || $repo === '' || $callbackBase === '') {
            return [
                'dispatched' => false,
                'reason' => 'dispatcher_not_configured',
                'message' => 'Crawler dispatch is not configured; the job is retained as pending.',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->accept('application/vnd.github+json')
                ->timeout(20)
                ->post(
                    "https://api.github.com/repos/{$repo}/actions/workflows/{$workflow}/dispatches",
                    [
                        'ref' => 'main',
                        'inputs' => [
                            'job_id' => $job->uuid,
                            'target_url' => $job->website->normalized_url,
                            'callback_url' => rtrim($callbackBase, '/').'/api/internal/crawler-callback',
                        ],
                    ],
                );

            if ($response->status() !== 204) {
                Log::warning('GitHub Actions dispatch failed', ['job' => $job->uuid, 'status' => $response->status()]);

                return [
                    'dispatched' => false,
                    'reason' => 'dispatch_request_failed',
                    'message' => 'The crawler runner rejected the dispatch request; the job is retained as pending.',
                ];
            }
        } catch (Throwable $e) {
            Log::warning('GitHub Actions dispatch exception', ['job' => $job->uuid, 'error' => $e->getMessage()]);

            return [
                'dispatched' => false,
                'reason' => 'dispatch_request_failed',
                'message' => 'The crawler runner could not be reached; the job is retained as pending.',
            ];
        }

        $job->update([
            'status' => AnalysisJob::STATUS_DISPATCHED,
            'dispatched_at' => now(),
        ]);

        AuditLog::record('analysis_job.dispatched', $job->user_id, $job, ['runner' => 'github_actions']);

        return ['dispatched' => true, 'reason' => null, 'message' => 'Analysis dispatched to the crawler runner.'];
    }
}
