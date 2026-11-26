<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use App\Models\User;
use App\Models\Website;
use App\Services\Analysis\AnalysisPipeline;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleIntelligenceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Website $website;

    protected function setUp(): void
    {
        parent::setUp();
        $this->website = Website::factory()->forUrl('https://example.com')->create();
        $this->user = $this->website->user;
    }

    private function completedJobWithPage(): AnalysisJob
    {
        $job = AnalysisJobFactory::new()->forWebsite($this->website)->completed()->create();

        CrawledPage::create([
            'analysis_job_id' => $job->id,
            'url' => 'https://example.com/',
            'final_url' => 'https://example.com/',
            'http_status' => 200,
            'title' => 'Example',
            'meta' => ['description' => 'Demo meta description for testing'],
            'headings' => ['h1' => ['Example heading']],
            'text_length' => 900,
            'load_time_ms' => 500,
            'content_hash' => hash('sha256', 'module-api-test'),
            'features' => [
                ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 2, 'evidence' => ['Get started', 'Book a demo']],
                ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']],
                ['feature_key' => 'mobile_viewport_meta', 'present' => true, 'count' => 1, 'evidence' => ['width=device-width']],
            ],
            'depth' => 0,
        ]);

        app(AnalysisPipeline::class)->run($job->refresh());

        return $job->refresh();
    }

    public function test_predictive_analytics_returns_persisted_opportunities_for_owner(): void
    {
        $job = $this->completedJobWithPage();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/predictive-analytics");

        $response->assertOk();
        $this->assertSame('conversion_opportunity', $response->json('module'));
        $this->assertSame($job->uuid, $response->json('analysis_job.uuid'));
        $this->assertNotEmpty($response->json('data.opportunities'));
        $this->assertNotNull($response->json('data.score'));
        $this->assertStringContainsString('not a validated conversion probability', (string) $response->json('data.breakdown.methodology'));
    }

    public function test_lead_intelligence_returns_persisted_results_for_owner(): void
    {
        $this->completedJobWithPage();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/lead-intelligence");

        $response->assertOk();
        $this->assertSame('lead_intelligence', $response->json('module'));
        $this->assertNotNull($response->json('data.score'));
        $this->assertNotEmpty($response->json('data.insights'));
    }

    public function test_wasted_spend_returns_findings_with_disclaimer_for_owner(): void
    {
        $this->completedJobWithPage();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/wasted-spend");

        $response->assertOk();
        $this->assertSame('wasted_spend', $response->json('module'));
        $this->assertIsArray($response->json('data.findings'));
        $this->assertStringContainsString('conversion-leakage indicators only', (string) $response->json('data.disclaimer'));
    }

    public function test_creative_intelligence_returns_groups_for_owner(): void
    {
        $this->completedJobWithPage();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/creative-intelligence");

        $response->assertOk();
        $this->assertSame('creative_intelligence', $response->json('module'));
        $this->assertNotEmpty($response->json('data.google_headlines'));
        $this->assertNotEmpty($response->json('data.google_descriptions'));
        $this->assertIsArray($response->json('data.message_match'));
        $this->assertStringContainsString('No CTR or conversion predictions', (string) $response->json('data.disclaimer'));
    }

    public function test_module_endpoints_are_blocked_for_other_users(): void
    {
        $this->completedJobWithPage();
        $intruder = User::factory()->create();

        foreach (['predictive-analytics', 'lead-intelligence', 'wasted-spend', 'creative-intelligence'] as $endpoint) {
            $this->actingAs($intruder, 'sanctum')
                ->getJson("/api/websites/{$this->website->id}/{$endpoint}")
                ->assertForbidden();
        }
    }

    public function test_module_endpoints_require_authentication(): void
    {
        foreach (['predictive-analytics', 'lead-intelligence', 'wasted-spend', 'creative-intelligence', 'budget-scenarios'] as $endpoint) {
            $this->getJson("/api/websites/{$this->website->id}/{$endpoint}")
                ->assertUnauthorized();
        }
    }

    public function test_module_endpoints_report_absence_of_analysis_honestly(): void
    {
        foreach (['predictive-analytics', 'lead-intelligence', 'wasted-spend', 'creative-intelligence'] as $endpoint) {
            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson("/api/websites/{$this->website->id}/{$endpoint}");

            $response->assertOk();
            $this->assertNull($response->json('data'));
        }
    }

    public function test_budget_scenarios_can_be_created_listed_by_owner(): void
    {
        $payload = [
            'label' => 'Spring push',
            'budget' => 3000,
            'cpc' => 1.5,
            'lead_rate' => 5,
            'conversion_rate' => 10,
            'average_order_value' => 200,
            'gross_margin' => 40,
            'target_cpa' => 400,
        ];

        $created = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/budget-scenarios", $payload);

        $created->assertCreated();
        $this->assertSame(3, count($created->json('results.scenarios')));
        $this->assertSame(2000, $created->json('results.scenarios.1.estimated_clicks'));
        $this->assertStringContainsString('not actual campaign performance', (string) $created->json('disclaimer'));

        $listed = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/budget-scenarios");

        $listed->assertOk();
        $this->assertCount(1, $listed->json('scenarios'));

        $this->assertDatabaseHas('budget_scenarios', [
            'website_id' => $this->website->id,
            'user_id' => $this->user->id,
            'label' => 'Spring push',
        ]);
    }

    public function test_budget_scenario_with_channel_applies_factors(): void
    {
        $created = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/budget-scenarios", [
                'budget' => 1000,
                'cpc' => 2,
                'lead_rate' => 4,
                'conversion_rate' => 10,
                'average_order_value' => 300,
                'gross_margin' => 40,
                'channel' => 'display',
            ]);

        $created->assertCreated();
        $this->assertSame(1250, $created->json('results.scenarios.1.estimated_clicks'));
    }

    public function test_budget_scenario_rejects_invalid_inputs(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/budget-scenarios", [
                'budget' => -5,
                'cpc' => 0,
                'lead_rate' => 200,
                'conversion_rate' => 10,
                'average_order_value' => 200,
                'gross_margin' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['budget', 'cpc', 'lead_rate', 'gross_margin']);
    }

    public function test_budget_scenarios_are_blocked_for_other_users(): void
    {
        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/budget-scenarios", [
                'budget' => 1000,
                'cpc' => 2,
                'lead_rate' => 4,
                'conversion_rate' => 10,
                'average_order_value' => 300,
                'gross_margin' => 40,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('budget_scenarios', ['website_id' => $this->website->id]);
    }

    public function test_channel_factors_endpoint_lists_static_factors(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/budget-channels');

        $response->assertOk();
        $this->assertCount(3, $response->json('channels'));
    }

    public function test_marketing_advisor_returns_engine_and_recommendations_for_owner(): void
    {
        $job = $this->completedJobWithPage();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/marketing-advisor");

        $response->assertOk();
        $this->assertSame('marketing_advisor', $response->json('module'));
        $this->assertSame($job->uuid, $response->json('analysis_job.uuid'));
        $this->assertSame('rule_based_v1', $response->json('data.breakdown.engine'));
        $this->assertNotNull($response->json('data.score'));
        $this->assertStringContainsString('not a large language model', (string) $response->json('data.breakdown.engine_note'));
        $this->assertNotEmpty($response->json('data.recommendations'));
    }

    public function test_marketing_advisor_returns_honest_empty_state_without_completed_analysis(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/marketing-advisor");

        $response->assertOk();
        $this->assertSame('marketing_advisor', $response->json('module'));
        $this->assertNull($response->json('data'));
        $this->assertNull($response->json('analysis_job'));
        $this->assertStringContainsString('No completed analysis yet', (string) $response->json('message'));
    }

    public function test_marketing_advisor_is_blocked_for_other_users(): void
    {
        $this->completedJobWithPage();

        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/marketing-advisor")
            ->assertForbidden();
    }

    public function test_marketing_advisor_requires_authentication(): void
    {
        $this->getJson("/api/websites/{$this->website->id}/marketing-advisor")
            ->assertUnauthorized();
    }
}
