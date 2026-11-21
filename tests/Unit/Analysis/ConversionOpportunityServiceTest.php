<?php

namespace Tests\Unit\Analysis;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use App\Models\Website;
use App\Services\Analysis\ConversionOpportunityService;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversionOpportunityServiceTest extends TestCase
{
    use RefreshDatabase;

    private const FEATURES = [
        ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 2, 'evidence' => ['Get started']],
        ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']],
        ['feature_key' => 'mobile_viewport_meta', 'present' => true, 'count' => 1, 'evidence' => ['width=device-width']],
        ['feature_key' => 'trust_signals', 'present' => true, 'count' => 4, 'evidence' => ['testimonial']],
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

    public function test_returns_ten_opportunities_with_aggregate_score(): void
    {
        $this->page();

        $result = app(ConversionOpportunityService::class)->analyze($this->job->refresh());

        $this->assertSame(10, count($result['opportunities']));
        $this->assertSame(21, $result['score']);
        $this->assertStringContainsString('not a validated conversion probability', $result['methodology']);
        $this->assertSame(
            ['cta_placement', 'form_complexity', 'content_clarity', 'value_proposition', 'navigation', 'landing_relevance', 'mobile_usability', 'trust_signals', 'contact_accessibility', 'page_performance'],
            array_column($result['opportunities'], 'key'),
        );
    }

    public function test_high_priority_when_no_cta_detected(): void
    {
        $features = array_map(
            fn (array $feature) => $feature['feature_key'] === 'cta_buttons'
                ? ['feature_key' => 'cta_buttons', 'present' => false, 'count' => 0, 'evidence' => []]
                : $feature,
            self::FEATURES,
        );

        $this->page(['features' => $features]);

        $result = app(ConversionOpportunityService::class)->analyze($this->job->refresh());

        $cta = $result['opportunities'][0];

        $this->assertSame('cta_placement', $cta['key']);
        $this->assertSame(90, $cta['score']);
        $this->assertSame('high', $cta['priority']);
        $this->assertSame('cta_placement', $result['high'][0]['key'] ?? null);
    }

    public function test_unknown_evidence_is_excluded_and_marked_null(): void
    {
        $this->page(['load_time_ms' => null]);

        $result = app(ConversionOpportunityService::class)->analyze($this->job->refresh());

        $performance = $result['opportunities'][9];

        $this->assertSame('page_performance', $performance['key']);
        $this->assertNull($performance['score']);
        $this->assertNull($performance['severity']);
        $this->assertSame('low', $performance['priority']);
    }

    public function test_high_and_medium_buckets_computed(): void
    {
        $features = array_map(
            fn (array $feature) => $feature['feature_key'] === 'cta_buttons'
                ? ['feature_key' => 'cta_buttons', 'present' => false, 'count' => 0, 'evidence' => []]
                : $feature,
            self::FEATURES,
        );

        $this->page(['features' => $features]);

        $result = app(ConversionOpportunityService::class)->analyze($this->job->refresh());

        $this->assertNotEmpty($result['high']);
        $this->assertNotEmpty($result['medium']);
    }
}
