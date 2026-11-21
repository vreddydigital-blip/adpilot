<?php

namespace Tests\Unit\Crawler;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use App\Models\CrawlError;
use App\Models\Website;
use App\Services\Crawler\CrawlerCallbackService;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlerCallbackServiceTest extends TestCase
{
    use RefreshDatabase;

    private CrawlerCallbackService $service;

    private Website $website;

    private AnalysisJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CrawlerCallbackService();
        $this->website = Website::factory()->forUrl('https://example.com')->create();
        $this->job = AnalysisJobFactory::new()->forWebsite($this->website)->create();
    }

    private function completedPayload(int $pageCount = 2): array
    {
        return [
            'status' => 'completed',
            'summary' => ['duration_ms' => 1500, 'pages_crawled' => $pageCount],
            'pages' => array_map(fn ($i) => [
                'url' => 'https://example.com/page-'.$i,
                'final_url' => 'https://example.com/page-'.$i,
                'http_status' => 200,
                'title' => 'Page '.$i,
                'meta' => ['description' => 'Demo page '.$i],
                'headings' => ['h1' => ['Heading '.$i]],
                'text_length' => 500 + $i,
                'load_time_ms' => 120,
                'depth' => min($i, 10),
                'features' => [
                    ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 2, 'evidence' => ['Get started']],
                    ['feature_key' => 'lead_forms', 'present' => false, 'count' => 0, 'evidence' => []],
                ],
            ], range(1, $pageCount)),
            'errors' => [
                ['error_type' => 'http_404', 'message' => 'Page returned HTTP 404.', 'url' => 'https://example.com/missing'],
            ],
        ];
    }

    public function test_started_callback_moves_job_to_crawling(): void
    {
        $result = $this->service->process($this->job, ['status' => 'started']);

        $this->assertSame(200, $result['status']);
        $this->assertSame('progress_recorded', $result['outcome']);
        $this->assertSame(AnalysisJob::STATUS_CRAWLING, $this->job->fresh()->status);
        $this->assertNotNull($this->job->fresh()->started_at);
    }

    public function test_completed_callback_persists_pages_and_errors(): void
    {
        $result = $this->service->process($this->job, $this->completedPayload(2));

        $this->assertSame(200, $result['status']);
        $this->assertSame('completed', $result['outcome']);
        $this->assertSame(AnalysisJob::STATUS_COMPLETED, $this->job->fresh()->status);
        $this->assertSame(2, $this->job->fresh()->crawled_pages);
        $this->assertSame(1500, $this->job->fresh()->crawl_duration_ms);
        $this->assertSame(2, CrawledPage::where('analysis_job_id', $this->job->id)->count());
        $this->assertSame(1, CrawlError::where('analysis_job_id', $this->job->id)->count());

        $page = CrawledPage::where('analysis_job_id', $this->job->id)->first();
        $this->assertSame('Page 1', $page->title);
        $this->assertSame('https://example.com/page-1', $page->final_url);

        $this->website->refresh();
        $this->assertNotNull($this->website->last_analyzed_at);
    }

    public function test_terminal_job_returns_409(): void
    {
        $this->job->update(['status' => AnalysisJob::STATUS_COMPLETED, 'finished_at' => now()]);

        $result = $this->service->process($this->job->refresh(), $this->completedPayload());

        $this->assertSame(409, $result['status']);
        $this->assertSame('job_already_terminal', $result['outcome']);
        $this->assertSame(0, CrawledPage::count());
    }

    public function test_invalid_payload_returns_422(): void
    {
        $result = $this->service->process($this->job, ['status' => 'completed', 'summary' => []]);

        $this->assertSame(422, $result['status']);
        $this->assertSame('invalid_payload', $result['outcome']);
        $this->assertNotSame(AnalysisJob::STATUS_COMPLETED, $this->job->fresh()->status);
    }

    public function test_payload_exceeding_page_cap_returns_422(): void
    {
        $result = $this->service->process($this->job, $this->completedPayload(21));

        $this->assertSame(422, $result['status']);
        $this->assertSame('invalid_payload', $result['outcome']);
    }

    public function test_host_mismatch_returns_422(): void
    {
        $payload = $this->completedPayload(1);
        $payload['pages'][0]['final_url'] = 'https://evil.example.net/page-1';

        $result = $this->service->process($this->job, $payload);

        $this->assertSame(422, $result['status']);
        $this->assertSame('host_mismatch', $result['outcome']);
    }

    public function test_failed_callback_marks_job_failed(): void
    {
        $result = $this->service->process($this->job, [
            'status' => 'failed',
            'failure_reason' => 'timeout',
            'error_message' => 'The crawl timed out.',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('failed_recorded', $result['outcome']);
        $this->assertSame(AnalysisJob::STATUS_FAILED, $this->job->fresh()->status);
        $this->assertSame('timeout', $this->job->fresh()->failure_reason);
        $this->assertNotNull($this->job->fresh()->finished_at);
    }

    public function test_unknown_status_returns_422(): void
    {
        $result = $this->service->process($this->job, ['status' => 'warp-speed']);

        $this->assertSame(422, $result['status']);
        $this->assertSame('invalid_status', $result['outcome']);
    }
}
