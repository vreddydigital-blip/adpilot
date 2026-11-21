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

class CampaignIntelligenceApiTest extends TestCase
{
    use RefreshDatabase;

    private const FEATURES = [
        ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 2, 'evidence' => ['Get started', 'Book a demo']],
        ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']],
        ['feature_key' => 'mobile_viewport_meta', 'present' => true, 'count' => 1, 'evidence' => ['width=device-width']],
        ['feature_key' => 'trust_signals', 'present' => true, 'count' => 4, 'evidence' => ['testimonial', 'guarantee']],
    ];

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
            'content_hash' => hash('sha256', 'https://example-dot-com'),
            'features' => self::FEATURES,
            'depth' => 0,
        ]);

        app(AnalysisPipeline::class)->run($job->refresh());

        return $job->refresh();
    }

    public function test_campaign_intelligence_returns_persisted_results_for_owner(): void
    {
        $job = $this->completedJobWithPage();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/campaign-intelligence");

        $response->assertOk();
        $response->assertJsonPath('campaign_readiness.version', 'v1.0');
        $response->assertJsonPath('campaign_readiness.methodology', 'heuristic');
        $this->assertNotNull($response->json('campaign_readiness.score'));
        $this->assertNotNull($response->json('technical_audit.score'));
        $this->assertCount(7, $response->json('campaign_readiness.breakdown.criteria'));
        $this->assertNotEmpty($response->json('insights'));
        $this->assertSame($job->uuid, $response->json('analysis_job.uuid'));
    }

    public function test_campaign_intelligence_is_blocked_for_other_users(): void
    {
        $this->completedJobWithPage();

        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/campaign-intelligence")
            ->assertForbidden();
    }

    public function test_campaign_intelligence_requires_authentication(): void
    {
        $this->getJson("/api/websites/{$this->website->id}/campaign-intelligence")
            ->assertUnauthorized();
    }

    public function test_campaign_intelligence_reports_absence_of_analysis_honestly(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/campaign-intelligence");

        $response->assertOk();
        $this->assertNull($response->json('data'));
        $this->assertStringContainsString('No completed analysis yet', (string) $response->json('message'));
    }
}
