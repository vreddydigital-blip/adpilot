<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\Website;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_stale_marks_old_pending_jobs_failed(): void
    {
        $website = Website::factory()->create();
        $stale = AnalysisJobFactory::new()->forWebsite($website)->create();
        $stale->forceFill(['created_at' => now()->subMinutes(60)])->save();

        $fresh = AnalysisJobFactory::new()->forWebsite($website)->create();

        $this->artisan('analysis:prune-stale')->expectsOutputToContain('Marked')->assertSuccessful();

        $this->assertSame(AnalysisJob::STATUS_FAILED, $stale->fresh()->status);
        $this->assertSame('timeout', $stale->fresh()->failure_reason);
        $this->assertSame(AnalysisJob::STATUS_PENDING, $fresh->fresh()->status);
    }

    public function test_prune_stale_respects_custom_minutes_option(): void
    {
        $website = Website::factory()->create();
        $job = AnalysisJobFactory::new()->forWebsite($website)->create();
        $job->forceFill(['created_at' => now()->subMinutes(20)])->save();

        $this->artisan('analysis:prune-stale', ['--minutes' => 10])->expectsOutputToContain('Marked')->assertSuccessful();
        $this->assertSame(AnalysisJob::STATUS_FAILED, $job->fresh()->status);
    }

    public function test_prune_stale_reports_when_nothing_is_stale(): void
    {
        Website::factory()->count(2)->create();

        $this->artisan('analysis:prune-stale')->expectsOutputToContain('No stale analysis jobs')->assertSuccessful();
    }

    public function test_run_local_fails_cleanly_for_unknown_job_uuid(): void
    {
        $this->artisan('analysis:run-local', ['--job' => '00000000-0000-0000-0000-000000000000'])
            ->expectsOutputToContain('No analysis job found')
            ->assertFailed();
    }
}
