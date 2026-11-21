<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalAudit extends Model
{
    protected $fillable = [
        'analysis_job_id',
        'website_id',
        'score',
        'checks',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'checks' => 'array',
            'score' => 'integer',
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
