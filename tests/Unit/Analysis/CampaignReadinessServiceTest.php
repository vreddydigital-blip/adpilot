<?php

namespace Tests\Unit\Analysis;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use App\Models\Website;
use App\Services\Analysis\CampaignReadinessService;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private CampaignReadinessService $service;

    private Website $website;

    private AnalysisJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CampaignReadinessService();
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
                ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 2, 'evidence' => ['Get started', 'Book a sample']],
                ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']],
                ['feature_key' => 'mobile_viewport_meta', 'present' => true, 'count' => 1, 'evidence' => ['width=device-width']],
                ['feature_key' => 'trust_signals', 'present' => true, 'count' => 3, 'evidence' => ['testimonial', 'guarantee', 'privacy policy']],
            ],
            'depth' => 0,
        ], $overrides);

        return CrawledPage::create([...$data, 'analysis_job_id' => $this->job->id]);
    }

    public function test_weights_sum_to_100(): void
    {
        $result = $this->service->evaluate($this->job);

        $this->assertSame(100, array_sum(array_column($result['criteria'], 'weight')));
    }

    public function test_score_is_reproducible_for_the_same_data(): void
    {
        $first = $this->service->evaluate($this->job);
        $second = $this->service->evaluate($this->job);

        $this->assertSame($first['score'], $second['score']);
        $this->assertSame($first['criteria'], $second['criteria']);
    }

    public function test_high_evidence_site_is_capped_below_100(): void
    {
        $this->page();

        $result = $this->service->evaluate($this->job);

        $this->assertNotNull($result['score']);
        $this->assertLessThan(100, $result['score']);
        $this->assertGreaterThan(60, $result['score']);
    }

    public function test_missing_crawl_evidence_produces_null_score(): void
    {
        $result = $this->service->evaluate($this->job);

        $this->assertNull($result['score']);
        $this->assertSame('low', $result['confidence']);
        $this->assertSame('No crawl evidence available, so no score is calculated.', $result['summary']);
    }

    public function test_criteria_with_missing_features_score_low_instead_of_unknown(): void
    {
        $this->page(['headings' => []]);

        $result = $this->service->evaluate($this->job);

        foreach ($result['criteria'] as $criterion) {
            $this->assertNotNull($criterion['score']);
        }

        $valueProposition = collect($result['criteria'])->first(fn ($c) => $c['key'] === 'value_proposition');
        $this->assertLessThan(60, $valueProposition['score']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_high_priority_insight_generated_for_zero_cta_site(): void
    {
        $this->page(['features' => []]);

        $result = $this->service->evaluate($this->job);
        $insights = collect($this->service->insights($result));

        $cta = $insights->first(fn ($insight) => stripos($insight['title'], 'cta effectiveness') !== false);

        $this->assertNotNull($cta);
        $this->assertSame('high', $cta['priority']);
        $this->assertSame('improvement', $cta['direction']);
        $this->assertNotSame('', $cta['recommendation']);
    }

    public function test_strong_criteria_do_not_generate_insights(): void
    {
        $this->page();

        $result = $this->service->evaluate($this->job);
        $insights = collect($this->service->insights($result));

        $this->assertTrue($insights->every(fn ($insight) => ! str_contains($insight['title'], 'mobile usability')));
    }

    public function test_every_criterion_carries_evidence_and_explanation(): void
    {
        $this->page();

        $result = $this->service->evaluate($this->job);

        foreach ($result['criteria'] as $criterion) {
            $this->assertArrayHasKey('evidence', $criterion);
            $this->assertArrayHasKey('explanation', $criterion);
            $this->assertArrayHasKey('confidence', $criterion);
            $this->assertArrayHasKey('recommendation', $criterion);
            $this->assertIsArray($criterion['evidence']);
        }

        $this->assertStringContainsString('not a validated ML prediction', $result['methodology']);
    }

    public function test_lead_capture_points_accumulate(): void
    {
        $this->page();

        $result = $this->service->evaluate($this->job);
        $lead = collect($result['criteria'])->first(fn ($criterion) => $criterion['key'] === 'lead_capture_readiness');

        $this->assertSame(40, $lead['score']);
    }
}
