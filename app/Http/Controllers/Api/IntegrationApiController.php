<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveIntegrationRequest;
use App\Models\AuditLog;
use App\Models\IntegrationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class IntegrationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->payload((int) $request->user()->id));
    }

    public function save(SaveIntegrationRequest $request, string $provider): JsonResponse
    {
        if (! in_array($provider, IntegrationSetting::providerKeys(), true)) {
            return response()->json([
                'message' => 'Unknown integration provider.',
                'supported' => IntegrationSetting::providerKeys(),
            ], 404);
        }

        $userId = (int) $request->user()->id;
        $credentials = $request->preparedCredentials();
        $existing = IntegrationSetting::where('user_id', $userId)->where('provider', $provider)->first();
        if ($existing && $existing->credentials !== null) {
            foreach ($credentials as $field => $value) {
                if ($value === null || $value === '') {
                    $credentials[$field] = $existing->credentials[$field] ?? null;
                }
            }
        }

        try {
            $setting = IntegrationSetting::updateOrCreate(
                ['user_id' => $userId, 'provider' => $provider],
                [
                    'status' => IntegrationSetting::STATUS_CREDENTIALS_SAVED,
                    'credentials' => $credentials,
                ]
            );
        } catch (Throwable $exception) {
            AuditLog::record('integration.save_failed', (int) $request->user()->id, null, [
                'provider' => $provider,
                'error' => mb_substr($exception->getMessage(), 0, 300),
            ], $request->ip(), $request->userAgent());

            return response()->json(['message' => 'Integration credentials could not be stored.'], 500);
        }

        AuditLog::record('integration.credentials_saved', (int) $request->user()->id, $setting, [
            'provider' => $provider,
            'credential_fields' => array_keys(array_filter($credentials, fn ($v) => $v !== null && $v !== '')),
        ], $request->ip(), $request->userAgent());

        return response()->json([
            'data' => $this->one($setting),
            'message' => 'Credentials stored encrypted. No live connection was made and no advertising data is fetched yet.',
        ], $setting->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $provider): JsonResponse
    {
        $setting = IntegrationSetting::where('user_id', (int) $request->user()->id)
            ->where('provider', $provider)
            ->first();

        if (! $setting) {
            return response()->json(['message' => 'No stored integration for this provider.'], 404);
        }

        $setting->update([
            'status' => IntegrationSetting::STATUS_NOT_CONNECTED,
            'credentials' => null,
        ]);

        AuditLog::record('integration.credentials_removed', (int) $request->user()->id, $setting, [
            'provider' => $provider,
        ], $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->one($setting), 'message' => 'Stored credentials removed.']);
    }

    private function payload(int $userId): array
    {
        $saved = IntegrationSetting::where('user_id', $userId)
            ->get()
            ->keyBy('provider');

        $providers = collect(IntegrationSetting::catalog())
            ->map(function (array $meta, string $key) use ($saved) {
                $setting = $saved->get($key);

                return [
                    'provider' => $key,
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                    'credential_fields' => $meta['credentials'],
                    'provides' => $meta['provides'],
                    'status' => $setting?->status ?? IntegrationSetting::STATUS_NOT_CONNECTED,
                    'credentials_saved' => (bool) ($setting && $setting->credentials !== null),
                    'live_connection' => false,
                    'data_fetched' => false,
                ];
            })
            ->values()
            ->all();

        return [
            'providers' => $providers,
            'note' => config('integrations.note'),
            'credential_fields' => array_keys(array_filter($saved->all(), fn ($s) => $s->credentials !== null)),
        ];
    }

    private function one(IntegrationSetting $setting): array
    {
        $meta = IntegrationSetting::catalog()[$setting->provider] ?? null;

        return [
            'provider' => $setting->provider,
            'label' => $meta['label'] ?? $setting->provider,
            'status' => $setting->status,
            'live_connection' => false,
            'data_fetched' => false,
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }
}
