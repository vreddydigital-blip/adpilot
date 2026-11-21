<?php

namespace App\Services\Crawler;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CallbackVerifier
{
    public const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function verify(Request $request): array
    {
        $secret = (string) config('services.crawler.callback_secret');

        if ($secret === '') {
            return $this->reject('callback_secret_not_configured', 'Internal callback endpoint is not configured.');
        }

        $timestamp = $request->header('X-AdPilot-Timestamp');
        $signature = $request->header('X-AdPilot-Signature');

        if (! is_string($timestamp) || ! is_string($signature) || $signature === '') {
            return $this->reject('missing_signature', 'Missing callback authentication headers.');
        }

        if (! preg_match('/^\d+$/', $timestamp)) {
            return $this->reject('invalid_timestamp', 'Callback timestamp is malformed.');
        }

        $skew = abs(now()->getTimestamp() - (int) $timestamp);

        if ($skew > self::TIMESTAMP_TOLERANCE_SECONDS) {
            return $this->reject('timestamp_out_of_range', 'Callback timestamp is outside the accepted window.');
        }

        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        if (! hash_equals($expected, $signature)) {
            return $this->reject('signature_mismatch', 'Callback signature verification failed.');
        }

        $nonce = hash('sha256', $signature.'.'.$timestamp.'.'.$rawBody);

        if (! Cache::add('crawler_cb_nonce:'.$nonce, 1, now()->addSeconds(self::TIMESTAMP_TOLERANCE_SECONDS * 2))) {
            return $this->reject('replayed_request', 'Callback has already been processed.');
        }

        return ['ok' => true, 'error' => null];
    }

    private function reject(string $reason, string $message): array
    {
        return ['ok' => false, 'error' => $message, 'reason' => $reason];
    }

    public function auditRejection(Request $request, array $result): void
    {
        AuditLog::record(
            'crawler_callback.rejected',
            null,
            null,
            ['reason' => $result['reason'] ?? 'unknown'],
            $request->ip(),
            $request->userAgent(),
        );
    }
}
