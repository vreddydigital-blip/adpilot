<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_forwarded_client_ip_is_recorded_when_proxies_are_trusted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['X-Forwarded-For' => '203.0.113.50'])
            ->from('/dashboard')
            ->post('/projects', ['name' => 'Proxy test']);

        $log = AuditLog::where('action', 'project.created')->firstOrFail();
        $this->assertSame('203.0.113.50', $log->ip_address);
    }

    public function test_direct_client_ip_is_recorded_without_forwarded_header(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/projects', ['name' => 'Direct test']);

        $log = AuditLog::where('action', 'project.created')->firstOrFail();
        $this->assertSame('127.0.0.1', $log->ip_address);
    }

    public function test_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
