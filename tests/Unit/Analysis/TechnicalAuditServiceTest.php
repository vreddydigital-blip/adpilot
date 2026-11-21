<?php

namespace Tests\Unit\Analysis;

use App\Models\AnalysisJob;
use App\Models\CrawlError;
use App\Models\CrawledPage;
use App\Models\Website;
use App\Services\Analysis\TechnicalAuditService;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicalAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private TechnicalAuditService $service;

    private Website $website;

    private AnalysisJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TechnicalAuditService();
        $this->website = Website::factory()->forUrl('https://example.com')->create();
        $this->job = AnalysisJobFactory::new()->forWebsite($this->website)->create();
    }

    private function page(array $overrides = []): CrawledPage
    {
        $url = $overrides['url'] ?? 'https://example.com/page'.fake()->unique()->numberBetween(1, 100);

        $data = array_merge([
            'url' => $url,
            'final_url' => $url,
            'http_status' => 200,
            'title' => 'Example page',
            'meta' => ['description' => 'Demo meta description for testing'],
            'headings' => ['h1' => ['Example heading']],
            'text_length' => 800,
            'load_time_ms' => 400,
            'content_hash' => hash('sha256', uniqid('', true)),
            'features' => [
                ['feature_key' => 'mobile_viewport_meta', 'present' => true, 'count' => 1, 'evidence' => ['width=device-width']],
            ],
            'depth' => 0,
        ], $overrides);

        return CrawledPage::create([...$data, 'analysis_job_id' => $this->job->id]);
    }

    public function test_https_check_fails_for_http_entry_page(): void
    {
        $this->page(['url' => 'http://example.com/home', 'final_url' => null]);

        $result = $this->service->audit($this->job);
        $https = collect($result['checks'])->firstWhere('key', 'https');

        $this->assertSame('fail', $https['status']);
    }

    public function test_high_failures_reduce_the_score(): void
    {
        $this->page([
            'url' => 'http://example.com/home',
            'final_url' => 'http://example.com/home',
            'http_status' => 500,
            'title' => null,
            'meta' => [],
            'load_time_ms' => 6000,
            'features' => [
                ['feature_key' => 'mobile_viewport_meta', 'present' => false, 'count' => 0, 'evidence' => []],
            ],
        ]);

        $result = $this->service->audit($this->job);

        $this->assertLessThan(50, $result['score']);
    }

    public function test_high_compliance_earns_high_score(): void
    {
        $this->page();

        $result = $this->service->audit($this->job);

        $this->assertGreaterThan(70, $result['score']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_load_time_unknown_when_no_timings_recorded(): void
    {
        $this->page(['load_time_ms' => null]);

        $result = $this->service->audit($this->job);
        $loadTime = collect($result['checks'])->firstWhere('key', 'load_time');

        $this->assertSame('unknown', $loadTime['status']);
        $this->assertNull($loadTime['value']);
    }

    public function test_empty_job_yields_null_score(): void
    {
        $result = $this->service->audit($this->job);

        $this->assertNull($result['score']);
        $this->assertSame('low', $result['confidence']);
    }

    public function test_crawl_errors_downgrade_the_audit(): void
    {
        $this->page();

        CrawlError::create([
            'analysis_job_id' => $this->job->id,
            'url' => 'https://example.com/missing',
            'error_type' => 'http_404',
            'message' => 'Not found',
            'occurred_at' => now(),
        ]);
        CrawlError::create([
            'analysis_job_id' => $this->job->id,
            'url' => 'https://example.com/etc',
            'error_type' => 'dns_failure',
            'message' => 'Resolution failed',
            'occurred_at' => now(),
        ]);

        $result = $this->service->audit($this->job);
        $errors = collect($result['checks'])->firstWhere('key', 'crawl_errors');

        $this->assertSame('warn', $errors['status']);
        $this->assertSame('2 errors across 1 pages', $errors['value']);
    }

    public function test_audit_is_reproducible(): void
    {
        $this->page();

        $first = $this->service->audit($this->job);
        $second = $this->service->audit($this->job);

        $this->assertSame($first['score'], $second['score']);
        $this->assertSame($first['checks'], $second['checks']);
    }
}
