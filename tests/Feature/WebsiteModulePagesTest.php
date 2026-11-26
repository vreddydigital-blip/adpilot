<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\BudgetScenario;
use App\Models\CrawledPage;
use App\Models\User;
use App\Models\Website;
use App\Services\Analysis\AnalysisPipeline;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteModulePagesTest extends TestCase
{
    use RefreshDatabase;

    private const FEATURES = [
        ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 2, 'evidence' => ['Get started', 'Book a demo']],
        ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']],
        ['feature_key' => 'mobile_viewport_meta', 'present' => true, 'count' => 1, 'evidence' => ['width=device-width']],
        ['feature_key' => 'trust_signals', 'present' => true, 'count' => 4, 'evidence' => ['testimonial', 'guarantee']],
        ['feature_key' => 'email_links', 'present' => true, 'count' => 1, 'evidence' => ['hello@example.com']],
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
            'content_hash' => hash('sha256', 'module-page-test'),
            'features' => self::FEATURES,
            'depth' => 0,
        ]);

        app(AnalysisPipeline::class)->run($job->refresh());

        return $job->refresh();
    }

    public function test_module_pages_render_results_for_owner(): void
    {
        $this->completedJobWithPage();

        $this->actingAs($this->user)->get(route('websites.predictive', $this->website))
            ->assertOk()
            ->assertSee('Heuristic Conversion Opportunity Score')
            ->assertSee('CTA placement');

        $this->actingAs($this->user)->get(route('websites.wasted-spend', $this->website))
            ->assertOk()
            ->assertSee('conversion-leakage indicators only', false);

        $this->actingAs($this->user)->get(route('websites.lead', $this->website))
            ->assertOk()
            ->assertSee('Heuristic Lead Capture Readiness Score');

        $this->actingAs($this->user)->get(route('websites.creative', $this->website))
            ->assertOk()
            ->assertSee('Google Search headlines');
    }

    public function test_module_pages_show_honest_empty_state_without_analysis(): void
    {
        foreach (['websites.predictive', 'websites.wasted-spend', 'websites.lead', 'websites.creative'] as $route) {
            $this->actingAs($this->user)->get(route($route, $this->website))
                ->assertOk()
                ->assertSee('No completed analysis yet');
        }
    }

    public function test_module_pages_are_blocked_for_other_users(): void
    {
        $intruder = User::factory()->create();

        foreach (['websites.predictive', 'websites.wasted-spend', 'websites.lead', 'websites.creative', 'websites.budget'] as $route) {
            $this->actingAs($intruder)->get(route($route, $this->website))->assertForbidden();
        }
    }

    public function test_module_pages_require_authentication(): void
    {
        $this->get(route('websites.predictive', $this->website))->assertRedirect(route('login'));
    }

    public function test_budget_page_shows_calculator_and_tabs(): void
    {
        $this->actingAs($this->user)->get(route('websites.budget', $this->website))
            ->assertOk()
            ->assertSee('Budget scenario calculator')
            ->assertSee('Save scenario')
            ->assertSee('Predictive Analytics')
            ->assertSee('No saved scenarios yet');
    }

    public function test_budget_scenario_can_be_submitted_and_deleted_from_web(): void
    {
        $this->actingAs($this->user)->post(route('websites.budget-scenarios.store', $this->website), [
            'label' => 'Web scenario',
            'budget' => 1000,
            'cpc' => 2,
            'lead_rate' => 4,
            'conversion_rate' => 10,
            'average_order_value' => 300,
            'gross_margin' => 40,
        ])->assertRedirect(route('websites.budget', $this->website));

        $scenario = BudgetScenario::where('website_id', $this->website->id)->where('label', 'Web scenario')->first();
        $this->assertNotNull($scenario);
        $this->assertSame(2, $scenario->results['scenarios'][1]['assumed_cpc']);

        $this->actingAs($this->user)->get(route('websites.budget', $this->website))
            ->assertOk()
            ->assertSee('Web scenario');

        $this->actingAs($this->user)
            ->delete(route('websites.budget-scenarios.destroy', [$this->website, $scenario]))
            ->assertRedirect(route('websites.budget', $this->website));

        $this->assertDatabaseMissing('budget_scenarios', ['id' => $scenario->id]);
    }

    public function test_budget_scenario_cannot_be_deleted_by_another_user(): void
    {
        $scenario = BudgetScenario::create([
            'website_id' => $this->website->id,
            'user_id' => $this->user->id,
            'label' => 'Owner scenario',
            'inputs' => ['budget' => 1000],
            'results' => ['scenarios' => []],
        ]);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->delete(route('websites.budget-scenarios.destroy', [$this->website, $scenario]))
            ->assertForbidden();

        $this->assertDatabaseHas('budget_scenarios', ['id' => $scenario->id]);
    }

    public function test_website_show_page_includes_module_navigation(): void
    {
        $this->actingAs($this->user)->get(route('websites.show', $this->website))
            ->assertOk()
            ->assertSee('Predictive Analytics')
            ->assertSee('Budget Intelligence')
            ->assertSee('Wasted Spend')
            ->assertSee('Lead Intelligence')
            ->assertSee('Ad Creative')
            ->assertSee('AI Advisor');
    }

    public function test_advisor_page_renders_action_plan_for_owner(): void
    {
        $this->completedJobWithPage();

        $this->actingAs($this->user)->get(route('websites.advisor', $this->website))
            ->assertOk()
            ->assertSee('Executive summary')
            ->assertSee('Priority score')
            ->assertSee('Prioritised action plan')
            ->assertSee('not a large language model', false)
            ->assertSee('AI Advisor');
    }

    public function test_advisor_page_shows_empty_state_without_completed_analysis(): void
    {
        $this->actingAs($this->user)->get(route('websites.advisor', $this->website))
            ->assertOk()
            ->assertSee('No completed analysis yet');
    }

    public function test_advisor_page_is_blocked_for_other_users(): void
    {
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('websites.advisor', $this->website))
            ->assertForbidden();
    }
}
