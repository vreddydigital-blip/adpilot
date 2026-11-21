<?php

namespace App\Support;

class UrlNormalizer
{
    public static function normalize(string $scheme, string $host, ?int $port, string $path, string $query): string
    {
        $isDefaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
        $portPart = ($port !== null && ! $isDefaultPort) ? ':'.$port : '';

        $path = '/'.ltrim($path, '/');

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $queryPart = $query !== '' ? '?'.$query : '';

        if ($path === '/') {
            $path = '';
        }

        return $scheme.'://'.$host.$portPart.$path.$queryPart;
    }

    public static function hash(string $normalizedUrl): string
    {
        return hash('sha256', $normalizedUrl);
    }
}
