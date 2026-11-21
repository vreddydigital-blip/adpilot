<?php

namespace Tests\Unit\Analysis;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use App\Models\Website;
use App\Services\Analysis\LeadReadinessService;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private const FEATURES = [
        ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']],
        ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 1, 'evidence' => ['Get started']],
        ['feature_key' => 'email_links', 'present' => true, 'count' => 1, 'evidence' => ['a@example.com']],
    ];

    private AnalysisJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $website = Website::factory()->forUrl('https://example.com')->create();
        $this->job = AnalysisJobFactory::new()->forWebsite($website)->create();
    }

    private function page(array $overrides = []): CrawledPage
    {
        $data = array_merge([
            'url' => 'https://example.com/',
            'final_url' => 'https://example.com/',
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

    public function test_parses_crawler_form_evidence_strings(): void
    {
        $this->page();

        $result = app(LeadReadinessService::class)->analyze($this->job->refresh());

        $this->assertSame('lead_intelligence', $result['module_key']);
        $this->assertSame(70, $result['score']);
        $this->assertCount(1, $result['forms']);
        $this->assertSame(2, $result['forms'][0]['field_count']);
        $this->assertSame('post', $result['forms'][0]['method']);
        $this->assertTrue($result['contact_methods']['email']);
        $this->assertFalse($result['contact_methods']['phone']);
    }

    public function test_unparseable_form_evidence_keeps_sample_only(): void
    {
        $features = array_map(
            fn (array $feature) => $feature['feature_key'] === 'lead_forms'
                ? ['feature_key' => 'lead forms', 'present' => true, 'count' => 1, 'evidence' => ['a form was here']]
                : $feature,
            self::FEATURES,
        );

        $features[0]['feature_key'] = 'lead_forms';

        $this->page(['features' => $features]);

        $result = app(LeadReadinessService::class)->analyze($this->job->refresh());

        $this->assertNull($result['forms'][0]['field_count']);
        $this->assertNull($result['forms'][0]['method']);
        $this->assertSame('a form was here', $result['forms'][0]['sample']);
    }

    public function test_heavy_form_flags_friction_insight(): void
    {
        $features = array_map(
            fn (array $feature) => $feature['feature_key'] === 'lead_forms'
                ? ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['6-field post form']]
                : $feature,
            self::FEATURES,
        );

        $this->page(['features' => $features]);

        $result = app(LeadReadinessService::class)->analyze($this->job->refresh());

        $keys = array_column($result['insights'], 'insight_key');

        $this->assertContains('form_friction', $keys);
        $this->assertSame(60, $result['score']);
    }

    public function test_missing_contact_channels_can_be_high_priority(): void
    {
        $features = [['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']]];

        $this->page(['features' => $features]);

        $result = app(LeadReadinessService::class)->analyze($this->job->refresh());

        $insight = null;

        foreach ($result['insights'] as $item) {
            if ($item['insight_key'] === 'missing_contact_channels') {
                $insight = $item;
            }
        }

        $this->assertNotNull($insight);
        $this->assertSame('high', $insight['priority']);
        $this->assertGreaterThanOrEqual(3, count($insight['evidence']));
    }

    public function test_no_pages_returns_null_score_with_low_confidence(): void
    {
        $result = app(LeadReadinessService::class)->analyze($this->job->refresh());

        $this->assertNull($result['score']);
        $this->assertSame('low', $result['confidence']);
        $this->assertStringContainsString('No crawl evidence available', $result['summary']);
        $this->assertSame([], $result['insights']);
    }
}
