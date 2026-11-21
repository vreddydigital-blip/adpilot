<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageFeature extends Model
{
    protected $fillable = [
        'crawled_page_id',
        'feature_key',
        'present',
        'count',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'present' => 'boolean',
            'evidence' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CrawledPage::class, 'crawled_page_id');
    }
}
