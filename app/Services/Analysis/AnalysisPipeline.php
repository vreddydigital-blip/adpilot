<?php

namespace App\Services\Analysis;

use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Models\CampaignInsight;
use App\Models\MarketingScore;
use App\Models\TechnicalAudit;
use Illuminate\Support\Facades\DB;
use Throwable;

class AnalysisPipeline
{
    public function __construct(
        private TechnicalAuditService $technicalAuditService,
        private CampaignReadinessService $campaignReadinessService,
    ) {
    }

    public function run(AnalysisJob $job): array
    {
        if ($job->crawledPages()->count() === 0) {
            return ['ran' => false, 'reason' => 'no_pages'];
        }

        if ($job->status !== AnalysisJob::STATUS_PROCESSING) {
            $job->update(['status' => AnalysisJob::STATUS_PROCESSING]);
        }

        try {
            $result = DB::transaction(function () use ($job): array {
                $technical = $this->technicalAuditService->audit($job);

                $audit = TechnicalAudit::updateOrCreate(
                    ['analysis_job_id' => $job->id],
                    [
                        'website_id' => $job->website_id,
                        'score' => $technical['score'],
                        'checks' => $technical['checks'],
                        'version' => TechnicalAuditService::VERSION,
                    ],
                );

                $readiness = $this->campaignReadinessService->evaluate($job);

                $score = MarketingScore::updateOrCreate(
                    ['analysis_job_id' => $job->id, 'module_key' => CampaignReadinessService::MODULE_KEY],
                    [
                        'website_id' => $job->website_id,
                        'score' => $readiness['score'],
                        'methodology' => 'heuristic',
                        'version' => $readiness['version'],
                        'breakdown' => [
                            'criteria' => $readiness['criteria'],
                            'confidence' => $readiness['confidence'],
                            'methodology' => $readiness['methodology'],
                            'summary' => $readiness['summary'],
                        ],
                        'computed_at' => now(),
                    ],
                );

                $insights = [
                    ...$this->campaignReadinessService->insights($readiness),
                    ...$this->campaignReadinessService->technicalInsights($technical['checks']),
                ];

                CampaignInsight::where('analysis_job_id', $job->id)->delete();

                foreach ($insights as $insight) {
                    CampaignInsight::create([...$insight, 'website_id' => $job->website_id, 'analysis_job_id' => $job->id]);
                }

                return [
                    'technical_score' => $audit->score,
                    'campaign_score' => $score->score,
                    'insights' => count($insights),
                    'confidence' => $readiness['confidence'],
                ];
            });

            if (! $job->isCompleted()) {
                $job->update(['status' => AnalysisJob::STATUS_COMPLETED, 'finished_at' => $job->finished_at ?? now()]);
            }

            AuditLog::record('analysis_pipeline.completed', $job->user_id, $job, $result);

            return ['ran' => true, ...$result];
        } catch (Throwable $e) {
            AuditLog::record('analysis_pipeline.failed', $job->user_id, $job, ['error' => mb_substr($e->getMessage(), 0, 500)]);

            if (! $job->isCompleted()) {
                $job->update(['status' => AnalysisJob::STATUS_COMPLETED, 'finished_at' => $job->finished_at ?? now()]);
            }

            return ['ran' => false, 'reason' => 'pipeline_error', 'error' => $e->getMessage()];
        }
    }
}
