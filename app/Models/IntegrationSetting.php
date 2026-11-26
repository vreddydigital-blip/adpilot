<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSetting extends Model
{
    public const STATUS_NOT_CONNECTED = 'not_connected';

    public const STATUS_CREDENTIALS_SAVED = 'credentials_saved';

    protected $fillable = [
        'user_id',
        'provider',
        'status',
        'credentials',
        'settings',
        'connected_at',
        'last_synced_at',
    ];

    protected $casts = [
        'credentials' => 'encrypted:json',
        'settings' => 'array',
        'connected_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function catalog(): array
    {
        return config('integrations.providers');
    }

    public static function providerKeys(): array
    {
        return array_keys(static::catalog());
    }
}
