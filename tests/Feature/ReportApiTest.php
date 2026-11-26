<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use App\Models\Report;
use App\Models\User;
use App\Models\Website;
use App\Services\Analysis\AnalysisPipeline;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportApiTest extends TestCase
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
            'content_hash' => hash('sha256', 'report-api-test'),
            'features' => self::FEATURES,
            'depth' => 0,
        ]);

        app(AnalysisPipeline::class)->run($job->refresh());

        return $job->refresh();
    }

    public function test_owner_can_generate_pdf_and_list_reports(): void
    {
        $job = $this->completedJobWithPage();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/reports", ['format' => 'pdf']);

        $response->assertCreated();
        $this->assertSame('pdf', $response->json('report.format'));
        $this->assertSame($job->uuid, $response->json('report.analysis_job.uuid'));
        $this->assertDatabaseHas('reports', ['website_id' => $this->website->id, 'format' => 'pdf', 'status' => 'ready']);

        $list = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/websites/{$this->website->id}/reports");

        $list->assertOk();
        $this->assertCount(1, $list->json('reports'));

        $meta = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/reports/{$response->json('report.id')}");

        $meta->assertOk();
        $this->assertSame('dompdf', $meta->json('report.generator'));
    }

    public function test_generated_pdf_downloads_with_pdf_content_type(): void
    {
        $this->completedJobWithPage();

        $reportId = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/reports", ['format' => 'pdf'])
            ->json('report.id');

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/reports/{$reportId}/download");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('%PDF-', substr($response->getContent(), 0, 5));
    }

    public function test_generated_csv_downloads_with_sections(): void
    {
        $this->completedJobWithPage();

        $reportId = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/reports", ['format' => 'csv'])
            ->json('report.id');

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/reports/{$reportId}/download");

        $response->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('"AdPilot AI report"', $response->getContent());
    }

    public function test_report_generation_requires_completed_analysis(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/reports", ['format' => 'pdf'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No completed analysis yet. Reports require a completed crawl; run an analysis first.');

        $this->assertSame(0, Report::count());
    }

    public function test_report_endpoints_are_blocked_for_other_users(): void
    {
        $this->completedJobWithPage();

        $reportId = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/reports", ['format' => 'csv'])
            ->json('report.id');

        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/reports", ['format' => 'pdf'])
            ->assertForbidden();

        $this->actingAs($intruder, 'sanctum')
            ->get("/api/reports/{$reportId}")
            ->assertForbidden();

        $this->actingAs($intruder, 'sanctum')
            ->get("/api/reports/{$reportId}/download")
            ->assertForbidden();

        $this->assertSame(1, Report::count());
    }

    public function test_report_endpoints_require_authentication(): void
    {
        $this->postJson("/api/websites/{$this->website->id}/reports", ['format' => 'pdf'])
            ->assertUnauthorized();

        $this->getJson("/api/reports/1/download")
            ->assertUnauthorized();    }

    public function test_report_format_is_validated(): void
    {
        $this->completedJobWithPage();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/websites/{$this->website->id}/reports", ['format' => 'xlsx'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);
    }
}
