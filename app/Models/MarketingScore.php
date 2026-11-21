<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingScore extends Model
{
    protected $fillable = [
        'website_id',
        'analysis_job_id',
        'module_key',
        'score',
        'methodology',
        'version',
        'breakdown',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'breakdown' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class, 'analysis_job_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
