<?php

namespace Tests\Unit\Crawler;

use App\Services\Crawler\CallbackVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CallbackVerifierTest extends TestCase
{
    private CallbackVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new CallbackVerifier();
        config(['services.crawler.callback_secret' => 'test-callback-secret-123']);
    }

    private function makeRequest(string $body, ?string $timestamp, ?string $signature): Request
    {
        $request = Request::create('/api/internal/crawler-callback', 'POST', [], [], [], [], $body);
        $request->headers->set('content-type', 'application/json');

        if ($timestamp !== null) {
            $request->headers->set('X-AdPilot-Timestamp', $timestamp);
        }
        if ($signature !== null) {
            $request->headers->set('X-AdPilot-Signature', $signature);
        }

        return $request;
    }

    private function sign(string $body, ?int $timestamp = null, string $secret = 'test-callback-secret-123'): array
    {
        $timestamp = $timestamp ?? now()->getTimestamp();

        return [
            $timestamp,
            hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ];
    }

    public function test_rejects_when_secret_is_not_configured(): void
    {
        config(['services.crawler.callback_secret' => '']);

        [$timestamp, $signature] = $this->sign('{"a":1}');
        $result = $this->verifier->verify($this->makeRequest('{"a":1}', $timestamp, $signature));

        $this->assertFalse($result['ok']);
        $this->assertSame('callback_secret_not_configured', $result['reason']);
    }

    public function test_rejects_missing_headers(): void
    {
        $result = $this->verifier->verify($this->makeRequest('{"a":1}', null, null));

        $this->assertFalse($result['ok']);
        $this->assertSame('missing_signature', $result['reason']);
    }

    public function test_rejects_malformed_timestamp(): void
    {
        $result = $this->verifier->verify($this->makeRequest('{"a":1}', 'not-a-number', 'abcd'));

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_timestamp', $result['reason']);
    }

    public function test_rejects_timestamp_outside_tolerance(): void
    {
        $body = '{"a":1}';
        [$timestamp, $signature] = $this->sign($body, now()->getTimestamp() - CallbackVerifier::TIMESTAMP_TOLERANCE_SECONDS - 60);

        $result = $this->verifier->verify($this->makeRequest($body, $timestamp, $signature));

        $this->assertFalse($result['ok']);
        $this->assertSame('timestamp_out_of_range', $result['reason']);
    }

    public function test_rejects_signature_made_with_wrong_secret(): void
    {
        $body = '{"a":1}';
        [$timestamp, $signature] = $this->sign($body, null, 'wrong-secret');

        $result = $this->verifier->verify($this->makeRequest($body, $timestamp, $signature));

        $this->assertFalse($result['ok']);
        $this->assertSame('signature_mismatch', $result['reason']);
    }

    public function test_rejects_signature_over_modified_body(): void
    {
        $body = '{"a":1}';
        [$timestamp, $signature] = $this->sign($body);

        $result = $this->verifier->verify($this->makeRequest('{"a":999}', $timestamp, $signature));

        $this->assertFalse($result['ok']);
        $this->assertSame('signature_mismatch', $result['reason']);
    }

    public function test_accepts_valid_signature(): void
    {
        $body = '{"status":"completed"}';
        [$timestamp, $signature] = $this->sign($body);

        $result = $this->verifier->verify($this->makeRequest($body, $timestamp, $signature));

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
    }

    public function test_rejects_replayed_valid_request(): void
    {
        $body = '{"status":"failed"}';
        [$timestamp, $signature] = $this->sign($body);
        $request = $this->makeRequest($body, $timestamp, $signature);

        $this->assertTrue($this->verifier->verify($request)['ok']);
        $this->assertFalse($this->verifier->verify($request)['ok']);
    }

    public function test_timestamp_within_tolerance_is_accepted(): void
    {
        $body = '{"a":1}';
        [$timestamp, $signature] = $this->sign($body, now()->getTimestamp() - 250);

        $result = $this->verifier->verify($this->makeRequest($body, $timestamp, $signature));

        $this->assertTrue($result['ok']);
        Cache::flush();
    }
}
