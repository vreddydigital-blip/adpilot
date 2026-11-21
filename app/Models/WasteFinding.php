<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteFinding extends Model
{
    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_LOW = 'low';

    protected $fillable = [
        'website_id',
        'analysis_job_id',
        'finding_key',
        'severity',
        'title',
        'evidence',
        'potential_impact',
        'correction',
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
