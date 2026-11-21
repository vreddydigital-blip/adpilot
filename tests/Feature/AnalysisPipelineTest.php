<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\CampaignInsight;
use App\Models\CrawledPage;
use App\Models\MarketingScore;
use App\Models\TechnicalAudit;
use App\Models\Website;
use App\Services\Analysis\AnalysisPipeline;
use App\Services\Analysis\CampaignReadinessService;
use App\Services\Analysis\TechnicalAuditService;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisPipelineTest extends TestCase
{
    use RefreshDatabase;

    private const FEATURES = [
        ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 3, 'evidence' => ['Get started', 'Book a demo', 'Sign up']],
        ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']],
        ['feature_key' => 'mobile_viewport_meta', 'present' => true, 'count' => 1, 'evidence' => ['width=device-width']],
        ['feature_key' => 'trust_signals', 'present' => true, 'count' => 4, 'evidence' => ['testimonial', 'guarantee']],
        ['feature_key' => 'email_links', 'present' => true, 'count' => 1, 'evidence' => ['hello@example.com']],
    ];

    private AnalysisPipeline $pipeline;

    private Website $website;

    private AnalysisJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = app(AnalysisPipeline::class);
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
            'features' => self::FEATURES,
            'depth' => 0,
        ], $overrides);

        return CrawledPage::create([...$data, 'analysis_job_id' => $this->job->id]);
    }

    public function test_pipeline_persists_audit_scores_and_insights(): void
    {
        $this->page();

        $result = $this->pipeline->run($this->job->refresh());

        $this->assertTrue($result['ran']);
        $this->assertNotNull($result['campaign_score']);
        $this->assertNotNull(TechnicalAudit::where('analysis_job_id', $this->job->id)->first());
        $this->assertNotNull(MarketingScore::where('analysis_job_id', $this->job->id)->where('module_key', 'campaign_readiness')->first());
        $this->assertGreaterThan(0, CampaignInsight::where('analysis_job_id', $this->job->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'analysis_pipeline.completed']);
    }

    public function test_pipeline_refuses_job_without_pages(): void
    {
        $result = $this->pipeline->run($this->job->refresh());

        $this->assertFalse($result['ran']);
        $this->assertSame('no_pages', $result['reason']);
        $this->assertSame(0, MarketingScore::count());
    }

    public function test_pipeline_survives_updates_after_reprocess(): void
    {
        $this->page();

        $first = $this->pipeline->run($this->job->refresh());
        $before = CampaignInsight::where('analysis_job_id', $this->job->id)->count();

        $second = $this->pipeline->run($this->job->refresh());
        $after = CampaignInsight::where('analysis_job_id', $this->job->id)->count();

        $this->assertSame($first['campaign_score'], $second['campaign_score']);
        $this->assertSame(1, MarketingScore::where('analysis_job_id', $this->job->id)->count());
        $this->assertSame(1, TechnicalAudit::where('analysis_job_id', $this->job->id)->count());
        $this->assertSame($before, $after);
    }

    public function test_reprocess_command_reruns_and_reports_scores(): void
    {
        $this->page();
        $this->job->update(['status' => AnalysisJob::STATUS_COMPLETED, 'finished_at' => now()]);

        $this->artisan('analysis:reprocess', ['job' => $this->job->uuid])
            ->expectsOutputToContain('Campaign readiness score:')
            ->expectsOutputToContain('Technical audit score:')
            ->assertSuccessful();

        $this->artisan('analysis:reprocess', ['job' => '00000000-0000-0000-0000-000000000000'])
            ->expectsOutputToContain('No analysis job found')
            ->assertFailed();
    }

    public function test_pipeline_error_is_caught_and_audited(): void
    {
        $this->page();

        $auditService = $this->createMock(TechnicalAuditService::class);
        $auditService->method('audit')->willThrowException(new \RuntimeException('boom'));

        $pipeline = new AnalysisPipeline($auditService, new CampaignReadinessService());
        $result = $pipeline->run($this->job->refresh());

        $this->assertFalse($result['ran']);
        $this->assertSame('pipeline_error', $result['reason']);
        $this->assertSame(AnalysisJob::STATUS_COMPLETED, $this->job->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'analysis_pipeline.failed']);
    }
}
