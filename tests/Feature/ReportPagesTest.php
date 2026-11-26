<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use App\Models\User;
use App\Models\Website;
use App\Services\Analysis\AnalysisPipeline;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportPagesTest extends TestCase
{
    use RefreshDatabase;

    private const FEATURES = [
        ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 2, 'evidence' => ['Get started']],
        ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']],
        ['feature_key' => 'mobile_viewport_meta', 'present' => true, 'count' => 1, 'evidence' => ['width=device-width']],
    ];

    private User $user;

    private Website $website;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
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
            'content_hash' => hash('sha256', 'report-page-test'),
            'features' => self::FEATURES,
            'depth' => 0,
        ]);

        app(AnalysisPipeline::class)->run($job->refresh());

        return $job->refresh();
    }

    public function test_reports_page_renders_history_and_generate_buttons_for_owner(): void
    {
        $this->completedJobWithPage();

        $this->actingAs($this->user)->get(route('websites.reports', $this->website))
            ->assertOk()
            ->assertSee('Generate PDF report')
            ->assertSee('Generate CSV report')
            ->assertSee('Score history')
            ->assertSee('Stored reports')
            ->assertSee('No reports generated yet')
            ->assertSee('chart.umd.min.js', false)
            ->assertSee('heuristic crawl-evidence scores', false);
    }

    public function test_reports_page_shows_honest_empty_state_without_completed_analysis(): void
    {
        $this->actingAs($this->user)->get(route('websites.reports', $this->website))
            ->assertOk()
            ->assertSee('Reports require a completed crawl');
    }

    public function test_owner_can_generate_and_download_report_via_web(): void
    {
        $job = $this->completedJobWithPage();

        $this->actingAs($this->user)
            ->from(route('websites.reports', $this->website))
            ->post(route('websites.reports.store', $this->website), ['format' => 'csv'])
            ->assertRedirect(route('websites.reports', $this->website))
            ->assertSessionHas('success', ucfirst('csv').' report generated from analysis '.$job->uuid.'.');

        $report = $this->website->reports()->first();
        $this->assertNotNull($report);
        $this->assertSame('ready', $report->status);

        $this->actingAs($this->user)
            ->get(route('websites.reports.download', [$this->website, $report]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_reports_page_and_downloads_are_blocked_for_other_users(): void
    {
        $this->completedJobWithPage();

        $this->actingAs($this->user)
            ->post(route('websites.reports.store', $this->website), ['format' => 'pdf'])
            ->assertRedirect();

        $report = $this->website->reports()->first();
        $this->assertNotNull($report);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('websites.reports', $this->website))
            ->assertForbidden();

        $this->actingAs($intruder)->get(route('websites.reports.download', [$this->website, $report]))
            ->assertForbidden();
    }

    public function test_reports_page_requires_authentication(): void
    {
        $this->get(route('websites.reports', $this->website))
            ->assertRedirect(route('login'));
    }
}
