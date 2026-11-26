<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_listing_requires_authentication(): void
    {
        $this->getJson('/api/integrations')->assertUnauthorized();
        $this->putJson('/api/integrations/google_ads', ['credentials' => ['account_id' => 'x']])->assertUnauthorized();
        $this->deleteJson('/api/integrations/google_ads')->assertUnauthorized();
    }

    public function test_index_lists_catalog_with_honest_flags(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/integrations');

        $response->assertOk();
        $providers = collect($response->json('providers'));
        $this->assertSame(4, $providers->count());
        $this->assertSame(['google_ads', 'meta_ads', 'google_analytics', 'google_search_console'], $providers->pluck('provider')->all());
        $providers->each(function (array $provider) {
            $this->assertSame('not_connected', $provider['status']);
            $this->assertFalse($provider['live_connection']);
            $this->assertFalse($provider['data_fetched']);
        });
        $this->assertStringContainsString('fabricated', (string) $response->json('note'));
    }

    public function test_save_stores_credentials_encrypted_and_returns_created(): void
    {
        $secret = 'super-secret-access-token-123';

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/integrations/meta_ads', ['credentials' => ['account_id' => 'act_1', 'access_token' => $secret]]);

        $response->assertCreated();
        $this->assertSame('credentials_saved', $response->json('data.status'));
        $this->assertFalse($response->json('data.live_connection'));

        $raw = IntegrationSetting::query()->first();
        $this->assertStringNotContainsString($secret, (string) $raw->getRawOriginal('credentials'));
        $this->assertSame('super-secret-access-token-123', $raw->credentials['access_token']);
        $this->assertSame('act_1', $raw->credentials['account_id']);
    }

    public function test_audit_log_never_contains_credential_values(): void
    {
        $secret = 'another-very-secret-token-value';

        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/integrations/meta_ads', ['credentials' => ['access_token' => $secret]]);

        $log = AuditLog::where('action', 'integration.credentials_saved')->get();
        $this->assertSame(1, $log->count());
        $this->assertStringNotContainsString($secret, json_encode($log->first()->properties));
    }

    public function test_save_rejects_unknown_provider_and_drops_unlisted_fields(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/integrations/not_a_provider', ['credentials' => ['a' => 'b']])
            ->assertNotFound();

        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/integrations/google_ads', ['credentials' => ['customer_id' => '123', 'hacker_field' => 'evil']])
            ->assertCreated();

        $credentials = IntegrationSetting::query()->first()->credentials;
        $this->assertArrayNotHasKey('hacker_field', $credentials);
        $this->assertSame('123', $credentials['customer_id']);
    }

    public function test_save_replaces_credentials_and_blank_fields_keep_old_values(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/integrations/meta_ads', ['credentials' => ['account_id' => 'act_1', 'access_token' => 'token-one']])
            ->assertCreated();

        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/integrations/meta_ads', ['credentials' => ['account_id' => 'act_2']])
            ->assertOk();

        $credentials = IntegrationSetting::query()->first()->credentials;
        $this->assertSame('act_2', $credentials['account_id']);
        $this->assertSame('token-one', $credentials['access_token']);
        $this->assertSame(1, IntegrationSetting::count());
    }

    public function test_destroy_clears_credentials_and_resets_status(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/integrations/meta_ads', ['credentials' => ['access_token' => 'token-one']])
            ->assertCreated();

        $response = $this->actingAs($this->user, 'sanctum')->deleteJson('/api/integrations/meta_ads');

        $response->assertOk();
        $this->assertSame('not_connected', $response->json('data.status'));

        $setting = IntegrationSetting::query()->first();
        $this->assertNull($setting->credentials);
        $this->assertSame('not_connected', $setting->status);
        $this->assertSame(1, AuditLog::where('action', 'integration.credentials_removed')->count());
    }

    public function test_destroy_without_saved_integration_returns_404(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/integrations/google_ads')
            ->assertNotFound();
    }

    public function test_settings_are_users_scoped(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/integrations/meta_ads', ['credentials' => ['access_token' => 'token-one']])
            ->assertCreated();

        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder, 'sanctum')->getJson('/api/integrations');
        $this->assertSame('not_connected', collect($response->json('providers'))->firstWhere('provider', 'meta_ads')['status']);
    }
}
