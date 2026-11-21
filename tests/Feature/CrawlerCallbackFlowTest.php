<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Models\CrawledPage;
use App\Models\Website;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlerCallbackFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'feature-test-callback-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.crawler.callback_secret' => self::SECRET]);
    }

    private function signedPost(string $uri, array $payload, ?string $secret = null, ?int $timestamp = null)
    {
        $secret = $secret ?? self::SECRET;
        $timestamp = $timestamp ?? now()->getTimestamp();
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-ADPILOT-TIMESTAMP' => (string) $timestamp,
            'HTTP_X-ADPILOT-SIGNATURE' => $signature,
        ];

        return $this->call('POST', $uri, [], [], [], $server, $body);
    }

    private function createPendingJob(): array
    {
        $website = Website::factory()->forUrl('https://example.com')->create();
        $job = AnalysisJobFactory::new()->forWebsite($website)->create();

        return [$website, $job];
    }

    private function completedPayload(string $jobUuid): array
    {
        return [
            'job_id' => $jobUuid,
            'status' => 'completed',
            'summary' => ['duration_ms' => 2100, 'pages_crawled' => 2],
            'pages' => [
                [
                    'url' => 'https://example.com',
                    'final_url' => 'https://example.com',
                    'http_status' => 200,
                    'title' => 'Example — Home',
                    'meta' => ['description' => 'Demo homepage'],
                    'headings' => ['h1' => ['Welcome'], 'h2' => ['Features']],
                    'text_length' => 1800,
                    'load_time_ms' => 240,
                    'depth' => 0,
                    'features' => [
                        ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 3, 'evidence' => ['Get started', 'Sign up']],
                    ],
                ],
                [
                    'url' => 'https://example.com/pricing',
                    'final_url' => 'https://example.com/pricing',
                    'http_status' => 200,
                    'title' => 'Pricing',
                    'meta' => [],
                    'headings' => ['h1' => ['Pricing']],
                    'text_length' => 900,
                    'load_time_ms' => 150,
                    'depth' => 1,
                    'features' => [
                        ['feature_key' => 'lead_forms', 'present' => true, 'count' => 1, 'evidence' => ['2-field post form']],
                    ],
                ],
            ],
            'errors' => [
                ['error_type' => 'http_404', 'message' => 'Page returned HTTP 404.', 'url' => 'https://example.com/old'],
            ],
        ];
    }

    public function test_signed_completed_callback_round_trip(): void
    {
        [, $job] = $this->createPendingJob();

        $response = $this->signedPost('/api/internal/crawler-callback', $this->completedPayload($job->uuid));

        $response->assertStatus(200)->assertJson(['outcome' => 'completed']);

        $job->refresh();
        $this->assertSame(AnalysisJob::STATUS_COMPLETED, $job->status);
        $this->assertSame(2, $job->crawled_pages);
        $this->assertSame(2100, $job->crawl_duration_ms);
        $this->assertSame(2, CrawledPage::where('analysis_job_id', $job->id)->count());
        $this->assertTrue(AuditLog::where('action', 'crawler_callback.completed')->where('auditable_id', $job->id)->exists());
    }

    public function test_forged_signature_is_rejected_and_audited(): void
    {
        [, $job] = $this->createPendingJob();

        $response = $this->signedPost('/api/internal/crawler-callback', $this->completedPayload($job->uuid), 'attacker-secret');

        $response->assertStatus(401);
        $this->assertTrue(AuditLog::where('action', 'crawler_callback.rejected')->exists());
        $this->assertNotSame(AnalysisJob::STATUS_COMPLETED, $job->fresh()->status);
    }

    public function test_replayed_callback_is_rejected(): void
    {
        [, $job] = $this->createPendingJob();

        $this->signedPost('/api/internal/crawler-callback', $this->completedPayload($job->uuid))->assertStatus(200);
        $this->signedPost('/api/internal/crawler-callback', $this->completedPayload($job->uuid))->assertStatus(401);
    }

    public function test_timestamp_from_outside_window_is_rejected(): void
    {
        [, $job] = $this->createPendingJob();

        $response = $this->signedPost('/api/internal/crawler-callback', $this->completedPayload($job->uuid), null, now()->getTimestamp() - 900);

        $response->assertStatus(401);
    }

    public function test_unknown_job_returns_404(): void
    {
        $response = $this->signedPost('/api/internal/crawler-callback', $this->completedPayload('00000000-0000-0000-0000-000000000000'));

        $response->assertStatus(404);
    }

    public function test_missing_job_id_returns_422(): void
    {
        $payload = $this->completedPayload('irrelevant');
        unset($payload['job_id']);

        $response = $this->signedPost('/api/internal/crawler-callback', $payload);

        $response->assertStatus(422);
    }

    public function test_non_json_body_returns_422(): void
    {
        $timestamp = now()->getTimestamp();
        $body = 'not-json';
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        $response = $this->call('POST', '/api/internal/crawler-callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-ADPILOT-TIMESTAMP' => (string) $timestamp,
            'HTTP_X-ADPILOT-SIGNATURE' => $signature,
        ], $body);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_users_cannot_access_sanctum_routes_after_callback(): void
    {
        [, $job] = $this->createPendingJob();

        $this->signedPost('/api/internal/crawler-callback', $this->completedPayload($job->uuid))->assertStatus(200);
        $this->getJson('/api/analysis-jobs/'.$job->id)->assertStatus(401);
    }
}
