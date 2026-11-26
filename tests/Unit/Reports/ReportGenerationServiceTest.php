<?php

namespace Tests\Unit\Reports;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use App\Models\Report;
use App\Models\Website;
use App\Services\Analysis\AnalysisPipeline;
use App\Services\Reports\ReportGenerationService;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const FEATURES = [
        ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 2, 'evidence' => ['Get started', 'Book a demo']],
        ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']],
        ['feature_key' => 'mobile_viewport_meta', 'present' => true, 'count' => 1, 'evidence' => ['width=device-width']],
    ];

    private AnalysisJob $job;

    private ReportGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $website = Website::factory()->forUrl('https://example.com')->create();
        $this->job = AnalysisJobFactory::new()->forWebsite($website)->completed()->create();
        CrawledPage::create([
            'analysis_job_id' => $this->job->id,
            'url' => 'https://example.com/',
            'final_url' => 'https://example.com/',
            'http_status' => 200,
            'title' => 'Example',
            'meta' => ['description' => 'Demo meta description for testing'],
            'headings' => ['h1' => ['Example heading']],
            'text_length' => 900,
            'load_time_ms' => 500,
            'content_hash' => hash('sha256', 'report-unit-test'),
            'features' => self::FEATURES,
            'depth' => 0,
        ]);
        app(AnalysisPipeline::class)->run($this->job->refresh());
        $this->service = app(ReportGenerationService::class);
    }

    public function test_snapshot_contains_scores_counts_and_disclaimer(): void
    {
        $snapshot = $this->service->snapshot($this->job->refresh());

        $this->assertSame('https://example.com', $snapshot['website']['url']);
        $this->assertSame($this->job->uuid, $snapshot['job']['uuid']);
        $this->assertSame(1, $snapshot['job']['pages']);
        $this->assertStringContainsString('never contains real advertising metrics', $snapshot['disclaimer']);

        $modules = array_column($snapshot['scores'], 'module');
        foreach (['technical_audit', 'campaign_readiness', 'conversion_opportunity', 'lead_intelligence', 'marketing_advisor'] as $module) {
            $this->assertContains($module, $modules);
        }

        $this->assertNotEmpty($snapshot['recommendations']);
        $this->assertNotEmpty($snapshot['findings']);
        $this->assertNotEmpty($snapshot['technical_checks']);
    }

    public function test_pdf_report_is_generated_and_stored(): void
    {
        $report = $this->service->generate($this->job->user, $this->job->website, $this->job->refresh(), 'pdf');

        $this->assertSame(Report::STATUS_READY, $report->status);
        $this->assertSame('dompdf', $report->generator);
        $this->assertStringEndsWith('.pdf', $report->filename);
        $this->assertGreaterThan(1000, $report->size_bytes);
        $this->assertSame('%PDF-', substr(Storage::disk('local')->get($report->path), 0, 5));
        $this->assertArrayHasKey('scores', $report->payload);
    }

    public function test_csv_report_contains_sections_and_disclaimer(): void
    {
        $report = $this->service->generate($this->job->user, $this->job->website, $this->job->refresh(), 'csv');

        $this->assertSame('fputcsv', $report->generator);
        $content = Storage::disk('local')->get($report->path);

        $this->assertStringContainsString('"AdPilot AI report"', $content);
        $this->assertStringContainsString('SECTION,"module scores (heuristic)"', $content);
        $this->assertStringContainsString('campaign_readiness', $content);
        $this->assertStringContainsString('SECTION,"advisor recommendations"', $content);
        $this->assertStringContainsString('never contains real advertising metrics', $content);
    }

    public function test_generate_refuses_without_completed_job(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $pendingJob = AnalysisJobFactory::new()->forWebsite($this->job->website)->create();

        $this->service->generate($this->job->user, $this->job->website, $pendingJob, 'pdf');
    }

    public function test_generate_refuses_unknown_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->generate($this->job->user, $this->job->website, $this->job->refresh(), 'xlsx');
    }

    public function test_ensure_file_regenerates_after_file_loss(): void
    {
        $report = $this->service->generate($this->job->user, $this->job->website, $this->job->refresh(), 'csv');

        Storage::disk('local')->delete($report->path);

        $path = $this->service->ensureFile($report->refresh());

        $this->assertSame($report->path, $path);
        $this->assertSame(Report::STATUS_READY, $report->refresh()->status);
        $this->assertStringContainsString('AdPilot AI report', Storage::disk('local')->get($path));
    }

    public function test_history_orders_jobs_chronologically_with_all_module_scores(): void
    {
        $website = $this->job->website;
        $this->job->update(['finished_at' => now()->subDay()]);

        $second = AnalysisJobFactory::new()->forWebsite($website)->create();
        CrawledPage::create([
            'analysis_job_id' => $second->id,
            'url' => 'https://example.com/',
            'final_url' => 'https://example.com/',
            'http_status' => 200,
            'title' => 'Example',
            'meta' => ['description' => 'Demo meta description for testing'],
            'headings' => ['h1' => ['Example heading']],
            'text_length' => 900,
            'load_time_ms' => 400,
            'content_hash' => hash('sha256', 'report-unit-test-2'),
            'features' => self::FEATURES,
            'depth' => 0,
        ]);
        app(AnalysisPipeline::class)->run($second->refresh());
        $second->update(['finished_at' => now()]);

        $history = $this->service->history($website);

        $this->assertCount(2, $history);
        $this->assertSame($this->job->uuid, $history[0]['uuid']);
        $this->assertSame($second->uuid, $history[1]['uuid']);

        foreach ($history as $row) {
            foreach (['campaign_readiness', 'technical_audit', 'conversion_opportunity', 'lead_intelligence', 'marketing_advisor'] as $module) {
                $this->assertNotNull($row['scores'][$module]);
            }
        }
    }
}
