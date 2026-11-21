<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\Project;
use App\Models\Website;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_works(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret-password-123',
            'password_confirmation' => 'secret-password-123',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs(User::where('email', 'test@example.com')->first());
    }

    public function test_login_and_logout_work(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_shows_real_counts_and_empty_states(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSeeText('Projects');
        $response->assertSeeText('No analysis has been submitted yet.');
        $response->assertSeeText('No projects yet.');
    }

    public function test_dashboard_uses_real_database_counts(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()->for($user)->create();
        $website = Website::factory()->for($project)->for($user, 'user')->create();

        AnalysisJob::factory()->count(2)->forWebsite($website)->create(['status' => AnalysisJob::STATUS_COMPLETED]);
        AnalysisJob::factory()->count(1)->forWebsite($website)->create(['status' => AnalysisJob::STATUS_PENDING]);
        AnalysisJob::factory()->count(3)->forWebsite($website)->create(['status' => AnalysisJob::STATUS_FAILED]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('>2</p>', false)
            ->assertSee('>1</p>', false)
            ->assertSee('>3</p>', false);
    }
}
