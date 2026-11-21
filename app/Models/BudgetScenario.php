<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetScenario extends Model
{
    protected $fillable = [
        'website_id',
        'user_id',
        'analysis_job_id',
        'label',
        'channel',
        'inputs',
        'results',
        'methodology',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'results' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class, 'analysis_job_id');
    }
}
