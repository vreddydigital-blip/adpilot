<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\Website;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_an_analysis_for_own_website(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->for($user, 'user')->create();

        $response = $this->actingAs($user)->post("/websites/{$website->id}/analyze");

        $response->assertRedirect();
        $this->assertDatabaseHas('analysis_jobs', [
            'website_id' => $website->id,
            'user_id' => $user->id,
            'status' => AnalysisJob::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'analysis_job.created']);
    }

    public function test_active_job_prevents_duplicates(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->for($user, 'user')->create();

        $this->actingAs($user)->post("/websites/{$website->id}/analyze");
        $this->actingAs($user)->post("/websites/{$website->id}/analyze");

        $this->assertDatabaseCount('analysis_jobs', 1);
    }

    public function test_new_job_can_be_created_after_failure(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->for($user, 'user')->create();

        AnalysisJob::factory()->forWebsite($website)->failed()->create();

        $this->actingAs($user)->post("/websites/{$website->id}/analyze");

        $this->assertDatabaseCount('analysis_jobs', 2);
    }

    public function test_unauthorized_user_cannot_start_analysis(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $website = Website::factory()->for($owner, 'user')->create();

        $this->actingAs($intruder)->post("/websites/{$website->id}/analyze")->assertForbidden();

        $this->assertDatabaseMissing('analysis_jobs', ['website_id' => $website->id]);
    }

    public function test_website_screen_shows_job_history_and_module_placeholder(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->for($user, 'user')->create();

        AnalysisJob::factory()->forWebsite($website)->failed()->create();

        $response = $this->actingAs($user)->get("/websites/{$website->id}");

        $response->assertStatus(200);
        $response->assertSeeText('Failed');
        $response->assertSeeText('Simulated failure for tests');
    }
}
