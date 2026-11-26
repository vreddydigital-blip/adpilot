<?php

namespace App\Console\Commands;

use App\Models\AnalysisJob;
use App\Services\Analysis\AnalysisPipeline;
use Illuminate\Console\Command;

class AnalysisReprocess extends Command
{
    protected $signature = 'analysis:reprocess {job : The analysis job UUID to re-run intelligence services for}';

    protected $description = 'Re-run all intelligence modules (technical, readiness, predictive, lead, waste, creative, advisor) for a completed analysis job';

    public function handle(AnalysisPipeline $pipeline): int
    {
        $job = AnalysisJob::where('uuid', $this->argument('job'))->first();

        if ($job === null) {
            $this->error('No analysis job found for UUID '.$this->argument('job'));

            return self::FAILURE;
        }

        if ($job->status !== AnalysisJob::STATUS_COMPLETED) {
            $this->error('Job '.$job->uuid.' is not completed (status: '.$job->status.'). Reprocessing requires a completed job.');

            return self::FAILURE;
        }

        $result = $pipeline->run($job);

        if (! ($result['ran'] ?? false)) {
            $this->error('Pipeline did not run. Reason: '.($result['reason'] ?? 'unknown'));

            return self::FAILURE;
        }

        $this->info('Campaign readiness score: '.($result['campaign_score'] ?? 'no score'));
        $this->info('Technical audit score: '.($result['technical_score'] ?? 'no score'));
        $this->info('Conversion opportunity score: '.($result['opportunity_score'] ?? 'no score'));
        $this->info('Lead readiness score: '.($result['lead_score'] ?? 'no score'));
        $this->info('Insights stored: '.$result['insights']);
        $this->info('Waste findings stored: '.$result['findings']);
        $this->info('Creative items stored: '.$result['creative_items']);
        $this->info('Priority score: '.($result['advisor_score'] ?? 'no score'));
        $this->info('Advisor recommendations stored: '.$result['recommendations']);
        $this->line('Confidence: '.$result['confidence']);

        return self::SUCCESS;
    }
}
