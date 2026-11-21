<?php

namespace App\Console\Commands;

use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Services\Crawler\CrawlerCallbackService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AnalysisRunLocal extends Command
{
    protected $signature = 'analysis:run-local
        {--job= : UUID of a specific pending job to run}
        {--all : Run every currently pending job}
        {--engine=http : Crawler engine to use (http or playwright)}
        {--timeout=280 : Max seconds allowed per crawl process}';

    protected $description = 'Run the local Node.js crawler for pending analysis jobs and feed the results through the callback service';

    public function handle(): int
    {
        $jobs = $this->resolveJobs();

        if ($jobs === null) {
            return self::FAILURE;
        }

        if ($jobs->isEmpty()) {
            $this->info('No pending analysis jobs to run.');

            return self::SUCCESS;
        }

        $exitCode = self::SUCCESS;

        foreach ($jobs as $job) {
            $this->line("Running job {$job->uuid} -> {$job->website->normalized_url}");
            $result = $this->runJob($job);

            $this->{$result['ok'] ? 'info' : 'error'}("  [{$result['outcome']}] {$result['message']}");

            if (! $result['ok']) {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }

    private function resolveJobs(): ?object
    {
        if ($uuid = $this->option('job')) {
            $jobs = AnalysisJob::with('website')
                ->where('uuid', $uuid)
                ->get();

            if ($jobs->isEmpty()) {
                $this->error("No analysis job found with UUID {$uuid}.");

                return null;
            }

            return $jobs;
        }

        if ($this->option('all')) {
            return AnalysisJob::with('website')
                ->where('status', AnalysisJob::STATUS_PENDING)
                ->oldest('id')
                ->get();
        }

        $this->error('Specify --job=<uuid> or --all.');

        return null;
    }

    private function runJob(AnalysisJob $job): array
    {
        $runnerPath = base_path((string) config('services.crawler.local_runner_path', 'crawler'));
        $crawlScript = $runnerPath.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'crawl.js';

        if (! is_file($crawlScript)) {
            return $this->failJob($job, 'local_runner_missing', "Crawler script not found at {$crawlScript}.");
        }

        $outputFile = tempnam(sys_get_temp_dir(), 'adpilot-crawl-').'.json';

        $process = new Process(array_filter([
            'node',
            $crawlScript,
            '--url',
            (string) $job->website->normalized_url,
            '--engine',
            (string) $this->option('engine'),
            '--max-pages',
            (string) CrawlerCallbackService::MAX_PAGES,
            '--job-id',
            $job->uuid,
            '--output',
            $outputFile,
        ]), $runnerPath);

        $process->setTimeout((float) $this->option('timeout'));
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput() ?: $process->getOutput());
            $message = mb_substr($error !== '' ? $error : 'Crawler process failed with no output.', 0, 1000);

            return $this->failJob($job, 'local_runner_error', $message);
        }

        if (! is_file($outputFile)) {
            return $this->failJob($job, 'local_runner_error', 'Crawler did not produce a result file.');
        }

        $raw = (string) file_get_contents($outputFile);
        @unlink($outputFile);

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return $this->failJob($job, 'invalid_crawler_output', 'Crawler output was not valid JSON.');
        }

        $outcome = app(CrawlerCallbackService::class)->process($job, $payload);

        return [
            'ok' => in_array($outcome['outcome'], ['completed', 'failed_recorded', 'progress_recorded'], true),
            'outcome' => $outcome['outcome'],
            'message' => $outcome['message'],
        ];
    }

    private function failJob(AnalysisJob $job, string $reason, string $message): array
    {
        app(CrawlerCallbackService::class)->process($job, [
            'status' => 'failed',
            'failure_reason' => $reason,
            'message' => $message,
        ]);

        AuditLog::record('analysis_job.local_runner_failure', null, $job, [
            'reason' => $reason,
        ]);

        return ['ok' => false, 'outcome' => $reason, 'message' => $message];
    }
}
