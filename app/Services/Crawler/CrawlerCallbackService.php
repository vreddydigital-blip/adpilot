<?php

namespace App\Services\Crawler;

use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Models\CrawlError;
use App\Models\CrawledPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CrawlerCallbackService
{
    public const MAX_PAGES = 20;

    public function process(AnalysisJob $job, array $payload): array
    {
        if ($job->isCompleted() || $job->isFailed() || $job->status === AnalysisJob::STATUS_CANCELLED) {
            return ['status' => 409, 'outcome' => 'job_already_terminal', 'message' => 'This job has already reached a terminal state.'];
        }

        $status = strtolower((string) ($payload['status'] ?? ''));

        return match ($status) {
            'started', 'progress' => $this->handleProgress($job, $status),
            'completed' => $this->handleCompleted($job, $payload),
            'failed' => $this->handleFailed($job, $payload),
            default => ['status' => 422, 'outcome' => 'invalid_status', 'message' => 'Unknown callback status.'],
        };
    }

    private function handleProgress(AnalysisJob $job, string $status): array
    {
        $job->update([
            'status' => AnalysisJob::STATUS_CRAWLING,
            'started_at' => $job->started_at ?? now(),
        ]);

        return ['status' => 200, 'outcome' => 'progress_recorded', 'message' => 'Crawl progress recorded.'];
    }

    private function handleCompleted(AnalysisJob $job, array $payload): array
    {
        $validator = Validator::make($payload, [
            'summary' => ['required', 'array'],
            'summary.duration_ms' => ['nullable', 'integer', 'between:0,2147483647'],
            'pages' => ['required', 'array', 'max:'.self::MAX_PAGES],
            'pages.*.url' => ['required', 'string', 'max:2048'],
            'pages.*.final_url' => ['nullable', 'string', 'max:2048'],
            'pages.*.http_status' => ['nullable', 'integer', 'between:0,599'],
            'pages.*.title' => ['nullable', 'string', 'max:512'],
            'pages.*.meta' => ['nullable', 'array'],
            'pages.*.headings' => ['nullable', 'array'],
            'pages.*.text_length' => ['nullable', 'integer', 'between:0,2147483647'],
            'pages.*.load_time_ms' => ['nullable', 'integer', 'between:0,2147483647'],
            'pages.*.depth' => ['nullable', 'integer', 'between:0,10'],
            'pages.*.features' => ['nullable', 'array'],
            'errors' => ['nullable', 'array', 'max:100'],
            'errors.*.error_type' => ['required', 'string', 'max:60'],
            'errors.*.message' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return ['status' => 422, 'outcome' => 'invalid_payload', 'message' => 'Crawl result failed validation.', 'errors' => $validator->errors()->all()];
        }

        $website = $job->website;
        $expectedHost = strtolower(parse_url($website->normalized_url, PHP_URL_HOST) ?? '');

        foreach ($payload['pages'] as $page) {
            $pageHost = strtolower((string) (parse_url($page['final_url'] ?? $page['url'], PHP_URL_HOST) ?? ''));

            if ($expectedHost === '' || $pageHost !== $expectedHost) {
                AuditLog::record('crawler_callback.host_mismatch', null, $job, ['page_host' => $pageHost]);

                return ['status' => 422, 'outcome' => 'host_mismatch', 'message' => 'Crawl result contains a URL outside the analysed website.'];
            }
        }

        DB::transaction(function () use ($job, $payload) {
            foreach ($payload['pages'] as $page) {
                $features = $page['features'] ?? null;

                $featuresSummary = null;

                if (is_array($features) && $features !== []) {
                    $featuresSummary = array_map(
                        fn ($item) => [
                            'feature_key' => mb_substr((string) ($item['feature_key'] ?? ''), 0, 100),
                            'present' => (bool) ($item['present'] ?? false),
                            'count' => (int) ($item['count'] ?? 0),
                            'evidence' => is_array($item['evidence'] ?? null) ? array_slice($item['evidence'], 0, 10) : null,
                        ],
                        $features,
                    );
                }

                CrawledPage::create([
                    'analysis_job_id' => $job->id,
                    'url' => $page['url'],
                    'final_url' => $page['final_url'] ?? null,
                    'http_status' => $page['http_status'] ?? null,
                    'title' => $page['title'] ?? null,
                    'meta' => $this->stringifyScalars($page['meta'] ?? null, 25),
                    'headings' => $this->normalizeHeadings($page['headings'] ?? null),
                    'text_length' => $page['text_length'] ?? null,
                    'load_time_ms' => $page['load_time_ms'] ?? null,
                    'content_hash' => hash('sha256', ($page['final_url'] ?? $page['url'])),
                    'features' => $featuresSummary,
                    'depth' => (int) ($page['depth'] ?? 0),
                ]);
            }

            foreach (($payload['errors'] ?? []) as $error) {
                CrawlError::create([
                    'analysis_job_id' => $job->id,
                    'url' => isset($error['url']) ? mb_substr((string) $error['url'], 0, 2048) : null,
                    'error_type' => mb_substr((string) $error['error_type'], 0, 60),
                    'message' => isset($error['message']) ? mb_substr((string) $error['message'], 0, 1000) : null,
                    'occurred_at' => now(),
                ]);
            }

            $job->update([
                'status' => AnalysisJob::STATUS_COMPLETED,
                'finished_at' => now(),
                'crawled_pages' => count($payload['pages']),
                'crawl_duration_ms' => $payload['summary']['duration_ms'] ?? null,
                'error_message' => null,
                'failure_reason' => null,
            ]);

            $job->website->update(['last_analyzed_at' => now()]);
        });

        AuditLog::record('crawler_callback.completed', $job->user_id, $job, ['pages' => count($payload['pages'])]);

        return ['status' => 200, 'outcome' => 'completed', 'message' => 'Crawl results stored.'];
    }

    private function stringifyScalars(mixed $values, int $limit): ?array
    {
        if (! is_array($values)) {
            return null;
        }

        $values = array_slice($values, 0, $limit);

        return collect($values)
            ->map(fn ($value) => is_scalar($value) ? (string) $value : null)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->values()
            ->all();
    }

    private function normalizeHeadings(mixed $headings): ?array
    {
        if (! is_array($headings)) {
            return null;
        }

        $normalized = [];

        foreach (['h1', 'h2', 'h3'] as $tag) {
            $values = $headings[$tag] ?? [];

            if (is_string($values)) {
                $values = [$values];
            }
            if (! is_array($values)) {
                continue;
            }

            $normalized[$tag] = collect($values)
                ->map(fn ($value) => is_scalar($value) ? trim((string) $value) : null)
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->take(30)
                ->values()
                ->all();
        }

        return $normalized === [] ? null : $normalized;
    }

    private function handleFailed(AnalysisJob $job, array $payload): array
    {
        $reason = mb_substr((string) ($payload['failure_reason'] ?? 'crawler_error'), 0, 120);
        $message = mb_substr((string) ($payload['error_message'] ?? 'Crawler reported a failure.'), 0, 2000);

        $job->update([
            'status' => AnalysisJob::STATUS_FAILED,
            'finished_at' => now(),
            'failure_reason' => $reason,
            'error_message' => $message,
        ]);

        AuditLog::record('crawler_callback.failed', $job->user_id, $job, ['failure_reason' => $reason]);

        return ['status' => 200, 'outcome' => 'failed_recorded', 'message' => 'Crawler failure recorded.'];
    }
}
