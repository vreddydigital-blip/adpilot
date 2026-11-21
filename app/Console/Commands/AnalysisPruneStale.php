<?php

namespace App\Console\Commands;

use App\Models\AnalysisJob;
use App\Models\AuditLog;
use Illuminate\Console\Command;

class AnalysisPruneStale extends Command
{
    protected $signature = 'analysis:prune-stale {--minutes= : Override the stale timeout in minutes}';

    protected $description = 'Mark pending or dispatched analysis jobs that exceeded the crawl timeout as failed';

    public function handle(): int
    {
        $minutes = (int) ($this->option('minutes') ?: config('services.crawler.stale_timeout_minutes', 30));
        $cutoff = now()->subMinutes(max(1, $minutes));

        $jobs = AnalysisJob::query()
            ->whereIn('status', [AnalysisJob::STATUS_PENDING, AnalysisJob::STATUS_DISPATCHED])
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($jobs->isEmpty()) {
            $this->info("No stale analysis jobs older than {$minutes} minutes.");

            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            $job->update([
                'status' => AnalysisJob::STATUS_FAILED,
                'error_message' => "The analysis job exceeded the {$minutes}-minute timeout without receiving crawler results.",
                'failure_reason' => 'timeout',
                'finished_at' => now(),
            ]);

            AuditLog::record('analysis_job.stale_timeout', null, $job, [
                'timeout_minutes' => $minutes,
                'previous_status' => $job->getOriginal('status'),
            ]);

            $this->warn("Marked job {$job->uuid} as failed (timeout).");
        }

        return self::SUCCESS;
    }
}
