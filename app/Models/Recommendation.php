<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_LOW = 'low';

    protected $fillable = [
        'website_id',
        'analysis_job_id',
        'recommendation_key',
        'priority',
        'title',
        'rationale',
        'evidence',
        'action',
        'effort',
        'expected_impact',
        'confidence',
        'module_source',
        'position',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'module_source' => 'array',
            'position' => 'integer',
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
