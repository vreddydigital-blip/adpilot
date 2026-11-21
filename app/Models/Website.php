<?php

namespace App\Models;

use App\Models\BudgetScenario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'url',
        'normalized_url',
        'url_hash',
        'host',
        'label',
        'last_analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_analyzed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analysisJobs(): HasMany
    {
        return $this->hasMany(AnalysisJob::class);
    }

    public function latestAnalysisJob(): HasOne
    {
        return $this->hasOne(AnalysisJob::class)->latestOfMany();
    }

    public function budgetScenarios(): HasMany
    {
        return $this->hasMany(BudgetScenario::class);
    }
}
