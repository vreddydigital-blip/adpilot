<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_redirects_guests_to_login(): void
    {
        $this->get('/integrations')->assertRedirect(route('login'));
    }

    public function test_page_lists_all_providers_as_not_connected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/integrations');

        $response->assertOk();
        $response->assertSeeText('Google Ads');
        $response->assertSeeText('Meta Ads');
        $response->assertSeeText('Google Analytics 4');
        $response->assertSeeText('Google Search Console');
        $response->assertSeeText('Not connected');
        $response->assertSeeText($this->esc(config('integrations.note')));
    }

    public function test_owner_can_save_credentials_and_sees_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/integrations')
            ->post('/integrations/meta_ads', ['credentials' => ['account_id' => 'act_1', 'access_token' => 'token-one']]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting = IntegrationSetting::query()->sole();
        $this->assertSame($user->id, $setting->user_id);
        $this->assertSame('meta_ads', $setting->provider);
        $this->assertSame('credentials_saved', $setting->status);

        $page = $this->actingAs($user)->get('/integrations');
        $page->assertSeeText('Credentials saved');
        $page->assertDontSeeText('token-one');
    }

    public function test_owner_can_remove_credentials(): void
    {
        $user = User::factory()->create();

        IntegrationSetting::create([
            'user_id' => $user->id,
            'provider' => 'meta_ads',
            'status' => IntegrationSetting::STATUS_CREDENTIALS_SAVED,
            'credentials' => ['access_token' => 'token-one'],
        ]);

        $response = $this->actingAs($user)
            ->from('/integrations')
            ->delete('/integrations/meta_ads');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting = IntegrationSetting::query()->sole();
        $this->assertNull($setting->credentials);
        $this->assertSame('not_connected', $setting->status);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
    }
}
