<?php

namespace Tests\Unit\Analysis;

use App\Models\AnalysisJob;
use App\Models\CampaignInsight;
use App\Models\LeadInsight;
use App\Models\MarketingScore;
use App\Models\TechnicalAudit;
use App\Models\WasteFinding;
use App\Models\Website;
use App\Services\Analysis\MarketingAdvisorService;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingAdvisorServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnalysisJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $website = Website::factory()->forUrl('https://example.com')->create();
        $this->job = AnalysisJobFactory::new()->forWebsite($website)->create();
    }

    private function scoreRow(string $moduleKey, int $score, array $breakdown = []): void
    {
        MarketingScore::create([
            'website_id' => $this->job->website_id,
            'analysis_job_id' => $this->job->id,
            'module_key' => $moduleKey,
            'score' => $score,
            'methodology' => 'heuristic',
            'version' => 'v1.0',
            'breakdown' => $breakdown,
        ]);
    }

    public function test_reports_engine_and_honest_labels(): void
    {
        $this->scoreRow('campaign_readiness', 50, ['confidence' => 'medium']);

        $result = app(MarketingAdvisorService::class)->advise($this->job->refresh());

        $this->assertSame('marketing_advisor', $result['module_key']);
        $this->assertSame('rule_based_v1', $result['engine']);
        $this->assertTrue($result['engine_configured']);
        $this->assertStringContainsString('not a large language model', $result['engine_note']);
        $this->assertStringContainsString('not a validated ML prediction', $result['engine_note']);
        $this->assertStringContainsString('never forecast spend, CPA, ROAS or revenue', $result['disclaimer']);
        $this->assertSame('medium', $result['scores']['confidence']);
    }

    public function test_advisor_score_is_100_minus_mean_of_known_module_scores(): void
    {
        $this->scoreRow('campaign_readiness', 45);
        $this->scoreRow('conversion_opportunity', 40);
        $this->scoreRow('lead_intelligence', 0);
        TechnicalAudit::create([
            'website_id' => $this->job->website_id,
            'analysis_job_id' => $this->job->id,
            'score' => 69,
            'checks' => [],
            'version' => 'v1.0',
        ]);

        $result = app(MarketingAdvisorService::class)->advise($this->job->refresh());

        $this->assertSame(61, $result['advisor_score']);
    }

    public function test_advisor_score_is_null_without_known_scores(): void
    {
        $result = app(MarketingAdvisorService::class)->advise($this->job->refresh());

        $this->assertNull($result['advisor_score']);
        $this->assertSame([], $result['recommendations']);
        $this->assertStringContainsString('No completed module results', $result['summary']);
    }

    public function test_consolidates_same_topic_findings_into_one_recommendation(): void
    {
        WasteFinding::create([
            'website_id' => $this->job->website_id,
            'analysis_job_id' => $this->job->id,
            'finding_key' => 'technical_viewport',
            'severity' => 'high',
            'title' => 'Pages missing the mobile viewport meta tag',
            'evidence' => ['1 of 2 pages missing viewport'],
            'correction' => 'Add a responsive viewport meta tag.',
        ]);

        CampaignInsight::create([
            'website_id' => $this->job->website_id,
            'analysis_job_id' => $this->job->id,
            'module_key' => 'campaign_readiness',
            'priority' => 'high',
            'title' => 'Mobile usability gap weakens paid landing pages',
            'evidence' => ['0 of 2 pages have viewport meta'],
            'recommendation' => 'Ship a mobile-first responsive layout.',
            'confidence' => 'high',
        ]);

        $result = app(MarketingAdvisorService::class)->advise($this->job->refresh());

        $mobile = array_values(array_filter($result['recommendations'], fn (array $item) => $item['recommendation_key'] === 'mobile_usability'));

        $this->assertCount(1, $mobile);
        $this->assertSame('high', $mobile[0]['priority']);
        $this->assertEqualsCanonicalizing(['wasted_spend', 'campaign_readiness'], $mobile[0]['module_source']);
        $this->assertStringContainsString('2 cross-module finding', $mobile[0]['rationale']);
    }

    public function test_orders_recommendations_by_priority_with_sequential_positions(): void
    {
        $rows = [
            ['technical_titles', 'high'],
            ['technical_cta_buttons', 'medium'],
            ['homepage_content', 'low'],
        ];

        foreach ($rows as [$key, $priority]) {
            WasteFinding::create([
                'website_id' => $this->job->website_id,
                'analysis_job_id' => $this->job->id,
                'finding_key' => $key,
                'severity' => $priority,
                'title' => 'Finding '.$key,
                'evidence' => ['Evidence for '.$key],
                'correction' => 'Fix '.$key,
            ]);
        }

        $result = app(MarketingAdvisorService::class)->advise($this->job->refresh());

        $this->assertSame(['high', 'medium', 'low'], array_column($result['recommendations'], 'priority'));
        $this->assertSame([1, 2, 3], array_column($result['recommendations'], 'position'));
        $this->assertSame(['high' => 1, 'medium' => 1, 'low' => 1], $result['priorities']);
    }

    public function test_caps_output_at_ten_recommendations(): void
    {
        for ($index = 1; $index <= 12; $index++) {
            WasteFinding::create([
                'website_id' => $this->job->website_id,
                'analysis_job_id' => $this->job->id,
                'finding_key' => 'finding_'.$index,
                'severity' => 'high',
                'title' => 'Finding number '.$index,
                'evidence' => ['Evidence '.$index],
                'correction' => 'Fix '.$index,
            ]);
        }

        $result = app(MarketingAdvisorService::class)->advise($this->job->refresh());

        $this->assertCount(10, $result['recommendations']);
        $this->assertSame(10, $result['priorities']['high']);
    }

    public function test_summary_mentions_scores_and_counts(): void
    {
        $this->scoreRow('campaign_readiness', 60);
        LeadInsight::create([
            'website_id' => $this->job->website_id,
            'analysis_job_id' => $this->job->id,
            'insight_key' => 'no_forms',
            'priority' => 'high',
            'title' => 'No lead capture form detected',
            'evidence' => ['No forms on any page'],
            'recommendation' => 'Add a short lead form.',
        ]);

        $result = app(MarketingAdvisorService::class)->advise($this->job->refresh());

        $this->assertStringContainsString('Campaign Readiness 60/100', $result['summary']);
        $this->assertStringContainsString('1 prioritised recommendation', $result['summary']);
        $this->assertSame('lead_capture', $result['recommendations'][0]['recommendation_key']);
    }
}
