<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveIntegrationRequest;
use App\Models\AuditLog;
use App\Models\IntegrationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) $request->user()->id;
        $saved = IntegrationSetting::where('user_id', $userId)->get()->keyBy('provider');

        $providers = collect(IntegrationSetting::catalog())
            ->map(function (array $meta, string $key) use ($saved) {
                $setting = $saved->get($key);

                return $meta + [
                    'provider' => $key,
                    'status' => $setting?->status ?? IntegrationSetting::STATUS_NOT_CONNECTED,
                    'credentials_saved' => (bool) ($setting && $setting->credentials !== null),
                    'saved_fields' => $setting && $setting->credentials !== null
                        ? array_keys(array_filter($setting->credentials, fn ($v) => $v !== null && $v !== ''))
                        : [],
                ];
            })
            ->values()
            ->all();

        return view('integrations.index', [
            'providers' => $providers,
            'note' => config('integrations.note'),
        ]);
    }

    public function save(SaveIntegrationRequest $request, string $provider): RedirectResponse
    {
        if (! in_array($provider, IntegrationSetting::providerKeys(), true)) {
            return back()->with('error', 'Unknown integration provider.');
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

            return back()->with('error', 'Integration credentials could not be stored.');
        }

        AuditLog::record('integration.credentials_saved', (int) $request->user()->id, $setting, [
            'provider' => $provider,
            'credential_fields' => array_keys(array_filter($credentials, fn ($v) => $v !== null && $v !== '')),
        ], $request->ip(), $request->userAgent());

        return back()->with('success', ucfirst(str_replace('_', ' ', $provider)).' credentials stored encrypted. No live connection is made yet and no advertising data is fetched.');
    }

    public function destroy(Request $request, string $provider): RedirectResponse
    {
        $setting = IntegrationSetting::where('user_id', (int) $request->user()->id)
            ->where('provider', $provider)
            ->first();

        if (! $setting) {
            return back()->with('error', 'No stored integration for this provider.');
        }

        $setting->update([
            'status' => IntegrationSetting::STATUS_NOT_CONNECTED,
            'credentials' => null,
        ]);

        AuditLog::record('integration.credentials_removed', (int) $request->user()->id, $setting, [
            'provider' => $provider,
        ], $request->ip(), $request->userAgent());

        return back()->with('success', 'Stored credentials for '.ucfirst(str_replace('_', ' ', $provider)).' were removed.');
    }
}
