<?php

namespace App\Services\Security;

use App\Support\UrlNormalizer;
use Illuminate\Support\Str;

class UrlValidator
{
    /**
     * Submission-time URL validation.
     *
     * This performs syntax and hostname-level checks. Full SSRF defence
     * (DNS resolution, redirect revalidation, private-IP blocking) is
     * enforced by the crawler at navigation time; a URL string alone can
     * never be trusted as sufficient.
     *
     * @return array{valid: bool, error: ?string, normalized_url: ?string, host: ?string, url_hash: ?string}
     */
    public function validate(string $url): array
    {
        $url = trim($url);

        if ($url === '' || mb_strlen($url) > 2048) {
            return $this->fail('The URL must be a non-empty string of at most 2048 characters.');
        }

        if (mb_detect_encoding($url, 'ASCII', true) === false && Str::contains($url, [' ', '<', '>', '"'])) {
            return $this->fail('The URL contains invalid characters.');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $this->fail('The URL must include both a scheme (http or https) and a host.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return $this->fail('Only http and https URLs are accepted.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return $this->fail('URLs with embedded credentials are not allowed.');
        }

        $host = strtolower($parts['host']);

        if (! $this->isAcceptableHost($host)) {
            return $this->fail(sprintf('The host "%s" is not an acceptable public website address.', $host));
        }

        $port = $parts['port'] ?? null;

        if ($port !== null && ($port < 1 || $port > 65535)) {
            return $this->fail('The URL contains an invalid port.');
        }

        $normalized = UrlNormalizer::normalize($scheme, $host, $port, $parts['path'] ?? '', $parts['query'] ?? '');

        return [
            'valid' => true,
            'error' => null,
            'normalized_url' => $normalized,
            'host' => $host,
            'url_hash' => hash('sha256', $normalized),
        ];
    }

    private function isAcceptableHost(string $host): bool
    {
        if (strlen($host) > 253) {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.test')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        if (in_array($host, ['metadata.google.internal', 'metadata.goog'], true)) {
            return false;
        }

        $labels = explode('.', $host);

        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63) {
                return false;
            }

            if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)$/', $label)) {
                return false;
            }
        }

        return count($labels) > 1 || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function fail(string $error): array
    {
        return [
            'valid' => false,
            'error' => $error,
            'normalized_url' => null,
            'host' => null,
            'url_hash' => null,
        ];
    }
}
