<?php

namespace Tests\Unit\Crawler;

use App\Models\AnalysisJob;
use App\Models\Website;
use App\Services\Crawler\GitHubActionsDispatcher;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalysisJobDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Website $website;

    private AnalysisJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->website = Website::factory()->forUrl('https://example.com')->create();
        $this->job = AnalysisJobFactory::new()->forWebsite($this->website)->create();
    }

    public function test_unconfigured_dispatcher_keeps_job_pending(): void
    {
        config(['services.crawler.github_token' => '']);

        $result = app(GitHubActionsDispatcher::class)->dispatch($this->job);

        $this->assertFalse($result['dispatched']);
        $this->assertSame('dispatcher_not_configured', $result['reason']);
        $this->assertSame(AnalysisJob::STATUS_PENDING, $this->job->fresh()->status);
    }

    public function test_successful_dispatch_moves_job_to_dispatched(): void
    {
        config([
            'services.crawler.github_token' => 'gh-token',
            'services.crawler.github_repo' => 'acme/adpilot-ai',
            'services.crawler.workflow' => 'crawler.yml',
            'services.crawler.callback_base_url' => 'https://adpilot.example.com',
        ]);

        Http::fake([
            'api.github.com/*' => Http::response(null, 204),
        ]);

        $result = app(GitHubActionsDispatcher::class)->dispatch($this->job);

        $this->assertTrue($result['dispatched']);
        $this->assertSame(AnalysisJob::STATUS_DISPATCHED, $this->job->fresh()->status);
        $this->assertNotNull($this->job->fresh()->dispatched_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.github.com/repos/acme/adpilot-ai/actions/workflows/crawler.yml/dispatches'
                && $request->header('Authorization')[0] === 'Bearer gh-token'
                && $request->data()['inputs']['job_id'] === $this->job->uuid
                && $request->data()['inputs']['target_url'] === $this->website->normalized_url
                && $request->data()['inputs']['callback_url'] === 'https://adpilot.example.com/api/internal/crawler-callback';
        });
    }

    public function test_failed_dispatch_keeps_job_pending(): void
    {
        config([
            'services.crawler.github_token' => 'gh-token',
            'services.crawler.github_repo' => 'acme/adpilot-ai',
            'services.crawler.callback_base_url' => 'https://adpilot.example.com',
        ]);

        Http::fake([
            'api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401),
        ]);

        $result = app(GitHubActionsDispatcher::class)->dispatch($this->job);

        $this->assertFalse($result['dispatched']);
        $this->assertSame('dispatch_request_failed', $result['reason']);
        $this->assertSame(AnalysisJob::STATUS_PENDING, $this->job->fresh()->status);
    }

    public function test_network_exception_keeps_job_pending(): void
    {
        config([
            'services.crawler.github_token' => 'gh-token',
            'services.crawler.github_repo' => 'acme/adpilot-ai',
            'services.crawler.callback_base_url' => 'https://adpilot.example.com',
        ]);

        Http::fake(fn () => throw new \RuntimeException('connection reset'));

        $result = app(GitHubActionsDispatcher::class)->dispatch($this->job);

        $this->assertFalse($result['dispatched']);
        $this->assertSame(AnalysisJob::STATUS_PENDING, $this->job->fresh()->status);
    }
}
