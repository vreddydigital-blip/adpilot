<?php

namespace Tests\Unit;

use App\Services\Security\UrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UrlValidatorTest extends TestCase
{
    private UrlValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new UrlValidator;
    }

    public static function invalidUrlProvider(): array
    {
        return [
            'empty' => [''],
            'no scheme' => ['example.com'],
            'ftp scheme' => ['ftp://example.com'],
            'javascript scheme' => ['javascript:alert(1)'],
            'file scheme' => ['file:///etc/passwd'],
            'missing host' => ['https://'],
            'localhost' => ['http://localhost'],
            'sub localhost' => ['http://app.localhost'],
            'dot local' => ['http://server.local'],
            'dot internal' => ['http://server.internal'],
            'dot test' => ['http://server.test'],
            'loopback ipv4' => ['http://127.0.0.1'],
            'loopback variant' => ['http://127.8.8.8'],
            'zero ip' => ['http://0.0.0.0'],
            'private 10' => ['http://10.0.0.5'],
            'private 172' => ['http://172.16.0.1'],
            'private 172 upper' => ['http://172.31.255.255'],
            'private 192' => ['http://192.168.1.1'],
            'link local' => ['http://169.254.169.254'],
            'gcp metadata' => ['http://metadata.google.internal/computeMetadata/v1/'],
            'gcp metadata2' => ['http://metadata.goog/'],
            'ipv6 loopback' => ['http://[::1]'],
            'ipv6 private' => ['http://[fe80::1]'],
            'ipv6 private 4' => ['http://[fc00::1]'],
            'embedded credentials' => ['http://user:pass@example.com'],
            'overlong host label' => ['https://'.str_repeat('a', 64).'.com'],
            'host too long' => ['https://'.str_repeat('a', 254).'.com'],
            'bad port' => ['https://example.com:99999'],
        ];
    }

    public static function validUrlProvider(): array
    {
        return [
            'plain https' => ['https://example.com', 'https://example.com'],
            'http' => ['http://example.com', 'http://example.com'],
            'with path' => ['https://example.com/pricing/', 'https://example.com/pricing'],
            'with query' => ['https://example.com/?utm_source=x', 'https://example.com?utm_source=x'],
            'default port stripped' => ['https://example.com:443', 'https://example.com'],
            'custom port kept' => ['http://example.com:8080', 'http://example.com:8080'],
            'uppercase host' => ['https://EXAMPLE.com', 'https://example.com'],
            'public ip' => ['https://93.184.216.34', 'https://93.184.216.34'],
        ];
    }

    #[DataProvider('validUrlProvider')]
    public function test_accepts_valid_public_urls(string $input, string $expectedNormalized): void
    {
        $result = $this->validator->validate($input);

        $this->assertTrue($result['valid'], 'Expected valid, got: '.($result['error'] ?? ''));
        $this->assertSame($expectedNormalized, $result['normalized_url']);
        $this->assertSame(hash('sha256', $expectedNormalized), $result['url_hash']);
    }

    #[DataProvider('invalidUrlProvider')]
    public function test_rejects_invalid_or_dangerous_urls(string $input): void
    {
        $result = $this->validator->validate($input);

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
        $this->assertNull($result['normalized_url']);
    }

    public function test_rejects_url_with_spaces(): void
    {
        $url = 'https://exa mple.com';
        $result = $this->validator->validate($url);
        $this->assertFalse($result['valid']);
    }
}
