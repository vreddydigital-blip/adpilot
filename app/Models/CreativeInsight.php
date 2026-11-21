<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeInsight extends Model
{
    protected $fillable = [
        'website_id',
        'analysis_job_id',
        'kind',
        'channel',
        'headline',
        'text',
        'char_length',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'char_length' => 'integer',
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
