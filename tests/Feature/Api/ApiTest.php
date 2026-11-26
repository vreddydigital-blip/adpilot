<?php

namespace Tests\Feature\Api;

use App\Models\AnalysisJob;
use App\Models\Project;
use App\Models\Website;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->postJson('/api/projects', ['name' => 'X'])->assertUnauthorized();
    }

    public function test_projects_crud_over_api_with_token(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user, 'sanctum')->postJson('/api/projects', [
            'name' => 'API Project',
            'description' => 'Created via API',
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.name', 'API Project');

        $project = Project::where('name', 'API Project')->firstOrFail();

        $index = $this->actingAs($user, 'sanctum')->getJson('/api/projects');
        $index->assertOk()->assertJsonCount(1, 'data');

        $show = $this->actingAs($user, 'sanctum')->getJson("/api/projects/{$project->id}");
        $show->assertOk();

        $update = $this->actingAs($user, 'sanctum')->putJson("/api/projects/{$project->id}", [
            'name' => 'Renamed via API',
        ]);
        $update->assertOk()->assertJsonPath('data.name', 'Renamed via API');

        $this->actingAs($user, 'sanctum')->deleteJson("/api/projects/{$project->id}")
            ->assertOk();
        $this->assertModelMissing($project);
    }

    public function test_cross_user_api_access_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $project = Project::factory()->for($owner)->create();
        $website = Website::factory()->for($owner, 'user')->create();

        $this->actingAs($intruder, 'sanctum')->getJson("/api/projects/{$project->id}")->assertForbidden();
        $this->actingAs($intruder, 'sanctum')->getJson("/api/websites/{$website->id}")->assertForbidden();
        $this->actingAs($intruder, 'sanctum')->postJson("/api/websites/{$website->id}/analyze")->assertForbidden();
        $this->assertDatabaseMissing('analysis_jobs', ['website_id' => $website->id]);
    }

    public function test_website_and_analysis_flow_over_api(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $store = $this->actingAs($user, 'sanctum')->postJson('/api/websites', [
            'project_id' => $project->id,
            'url' => 'https://example.com',
        ]);
        $store->assertCreated();
        $store->assertJsonPath('data.host', 'example.com');

        $website = Website::where('host', 'example.com')->firstOrFail();

        $analysis = $this->actingAs($user, 'sanctum')->postJson("/api/websites/{$website->id}/analyze");
        $analysis->assertStatus(202);
        $analysis->assertJsonPath('data.status', AnalysisJob::STATUS_PENDING);
        $analysis->assertJsonPath('job_created', true);

        $job = AnalysisJob::firstOrFail();

        $show = $this->actingAs($user, 'sanctum')->getJson("/api/analysis-jobs/{$job->id}");
        $show->assertOk()->assertJsonPath('data.status', AnalysisJob::STATUS_PENDING);
    }

    public function test_invalid_website_url_over_api_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $badUrl = 'http://'.chr(49).'27.0.0.1/admin';

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/websites', [
            'project_id' => $project->id,
            'url' => $badUrl,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
        $this->assertDatabaseCount('websites', 0);
    }
}
