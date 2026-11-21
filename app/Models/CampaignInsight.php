<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignInsight extends Model
{
    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_LOW = 'low';

    protected $fillable = [
        'website_id',
        'analysis_job_id',
        'module_key',
        'priority',
        'title',
        'evidence',
        'recommendation',
        'effort',
        'direction',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
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
