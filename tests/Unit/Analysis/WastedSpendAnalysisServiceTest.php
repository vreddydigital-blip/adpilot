<?php

namespace Tests\Unit\Analysis;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use App\Models\Website;
use App\Services\Analysis\WastedSpendAnalysisService;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WastedSpendAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnalysisJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $website = Website::factory()->forUrl('https://example.com')->create();
        $this->job = AnalysisJobFactory::new()->forWebsite($website)->create();
    }

    private function page(array $overrides = []): CrawledPage
    {
        return CrawledPage::create([...array_merge([
            'url' => 'https://example.com/',
            'final_url' => 'https://example.com/',
            'http_status' => 200,
            'title' => 'Example page',
            'meta' => ['description' => 'Demo meta description for testing'],
            'headings' => ['h1' => ['Example heading']],
            'text_length' => 800,
            'load_time_ms' => 400,
            'content_hash' => hash('sha256', uniqid('', true)),
            'features' => [],
            'depth' => 0,
        ], $overrides), 'analysis_job_id' => $this->job->id]);
    }

    private function technical(array $checks): array
    {
        return ['score' => 50, 'checks' => $checks, 'confidence' => 'medium'];
    }

    private function readiness(array $criteria): array
    {
        return ['module_key' => 'campaign_readiness', 'score' => 40, 'version' => 'v1.0', 'criteria' => $criteria, 'summary' => 'test'];
    }

    public function test_failed_technical_check_becomes_high_finding(): void
    {
        $this->page();

        $result = app(WastedSpendAnalysisService::class)->analyze($this->job->refresh(), $this->technical([
            ['key' => 'https', 'label' => 'HTTPS coverage', 'status' => 'fail', 'value' => '0/1', 'evidence' => ['a']],
        ]), $this->readiness([]));

        $this->assertCount(1, $result['findings']);
        $this->assertSame('technical_https', $result['findings'][0]['finding_key']);
        $this->assertSame('high', $result['findings'][0]['severity']);
        $this->assertStringContainsString('conversion-leakage indicators only', $result['disclaimer']);
    }

    public function test_warn_degrades_high_severity_to_medium(): void
    {
        $this->page();

        $result = app(WastedSpendAnalysisService::class)->analyze($this->job->refresh(), $this->technical([
            ['key' => 'https', 'label' => 'HTTPS coverage', 'status' => 'warn', 'evidence' => ['a']],
        ]), $this->readiness([]));

        $this->assertSame('medium', $result['findings'][0]['severity']);
    }

    public function test_unmapped_checks_are_ignored(): void
    {
        $this->page();

        $result = app(WastedSpendAnalysisService::class)->analyze($this->job->refresh(), $this->technical([
            ['key' => 'future_check', 'label' => 'Future', 'status' => 'fail', 'evidence' => []],
        ]), $this->readiness([]));

        $this->assertSame('no_obvious_leakage', $result['findings'][0]['finding_key']);
    }

    public function test_low_criterion_score_becomes_finding_with_escalation(): void
    {
        $this->page();

        $result = app(WastedSpendAnalysisService::class)->analyze($this->job->refresh(), $this->technical([]), $this->readiness([
            ['key' => 'lead_capture_readiness', 'label' => 'Lead capture readiness', 'weight' => 15, 'score' => 20, 'evidence' => ['e'], 'recommendation' => 'r', 'confidence' => 'medium'],
        ]));

        $this->assertSame('criterion_lead_capture_readiness', $result['findings'][0]['finding_key']);
        $this->assertSame('high', $result['findings'][0]['severity']);
    }

    public function test_healthy_criterion_score_is_ignored(): void
    {
        $this->page();

        $result = app(WastedSpendAnalysisService::class)->analyze($this->job->refresh(), $this->technical([]), $this->readiness([
            ['key' => 'lead_capture_readiness', 'label' => 'Lead capture readiness', 'weight' => 15, 'score' => 80, 'evidence' => [], 'recommendation' => '', 'confidence' => 'high'],
        ]));

        $this->assertSame('no_obvious_leakage', $result['findings'][0]['finding_key']);
    }
}
