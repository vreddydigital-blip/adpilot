<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_edit_and_delete_projects(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/projects', [
            'name' => 'Client A',
            'description' => 'Client A websites',
        ]);
        $response->assertStatus(302);

        $project = Project::where('name', 'Client A')->firstOrFail();
        $this->assertSame($user->id, $project->user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.created', 'auditable_id' => $project->id]);

        $this->actingAs($user)->patch("/projects/{$project->id}", [
            'name' => 'Client A Renamed',
        ])->assertRedirect(route('projects.show', $project, absolute: false));

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Client A Renamed']);

        $this->actingAs($user)->delete("/projects/{$project->id}")->assertRedirect(route('projects.index', absolute: false));
        $this->assertModelMissing($project);
    }

    public function test_duplicate_project_name_for_same_user_is_rejected(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Same Name']);

        $this->actingAs($user)->post('/projects', [
            'name' => 'Same Name',
        ])->assertSessionHasErrors('name');
    }

    public function test_unauthorized_user_cannot_view_edit_or_delete_projects(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)->get("/projects/{$project->id}")->assertForbidden();
        $this->actingAs($intruder)->get("/projects/{$project->id}/edit")->assertForbidden();
        $this->actingAs($intruder)->patch("/projects/{$project->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($intruder)->delete("/projects/{$project->id}")->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => $project->name]);
    }

    public function test_projects_index_only_shows_own_projects(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Project::factory()->for($user)->create(['name' => 'Mine 1']);
        Project::factory()->for($user)->create(['name' => 'Mine 2']);
        Project::factory()->for($other)->create(['name' => 'Not Mine']);

        $response = $this->actingAs($user)->get('/projects');

        $response->assertStatus(200);
        $response->assertSeeText('Mine 1');
        $response->assertSeeText('Mine 2');
        $response->assertDontSeeText('Not Mine');
    }

    public function test_websites_belong_to_project_and_cascade_on_delete(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $website = Website::factory()->for($project)->for($user, 'user')->create();

        $this->assertDatabaseHas('websites', ['id' => $website->id]);

        $project->delete();

        $this->assertDatabaseMissing('websites', ['id' => $website->id]);
    }

    public function test_unauthorized_user_cannot_add_websites_to_foreign_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)->post('/websites', [
            'project_id' => $project->id,
            'url' => 'https://example.com',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('websites', ['project_id' => $project->id]);
    }

    public function test_website_submission_requires_valid_url(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post('/websites', [
            'project_id' => $project->id,
            'url' => 'not a url',
        ])->assertSessionHasErrors(['url']);

        $this->actingAs($user)->post('/websites', [
            'project_id' => $project->id,
            'url' => 'http://localhost',
        ])->assertSessionHasErrors(['url']);

        $this->assertDatabaseCount('websites', 0);
    }

    public function test_website_submission_normalizes_and_persists(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post('/websites', [
            'project_id' => $project->id,
            'url' => 'https://EXAMPLE.com/pricing/',
        ])->assertRedirect();

        $this->assertDatabaseHas('websites', [
            'project_id' => $project->id,
            'host' => 'example.com',
            'normalized_url' => 'https://example.com/pricing',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'website.created']);
    }

    public function test_duplicate_website_within_project_is_not_duplicated(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post('/websites', [
            'project_id' => $project->id,
            'url' => 'https://example.com',
        ]);
        $this->actingAs($user)->post('/websites', [
            'project_id' => $project->id,
            'url' => 'https://example.com/',
        ]);

        $this->assertDatabaseCount('websites', 1);
    }

    public function test_unauthorized_user_cannot_view_foreign_website(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $website = Website::factory()->for($owner, 'user')->create();

        $this->actingAs($intruder)->get("/websites/{$website->id}")->assertForbidden();
        $this->actingAs($intruder)->delete("/websites/{$website->id}")->assertForbidden();
        $this->actingAs($intruder)->post("/websites/{$website->id}/analyze")->assertForbidden();
        $this->assertDatabaseMissing('analysis_jobs', ['website_id' => $website->id]);
    }
}
