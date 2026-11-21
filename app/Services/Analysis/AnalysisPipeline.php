<?php

namespace App\Services\Analysis;

use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Models\CampaignInsight;
use App\Models\CreativeInsight;
use App\Models\LeadInsight;
use App\Models\MarketingScore;
use App\Models\PredictiveInsight;
use App\Models\TechnicalAudit;
use App\Models\WasteFinding;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class AnalysisPipeline
{
    public function __construct(
        private TechnicalAuditService $technicalAuditService,
        private CampaignReadinessService $campaignReadinessService,
        private ConversionOpportunityService $conversionOpportunityService,
        private LeadReadinessService $leadReadinessService,
        private WastedSpendAnalysisService $wastedSpendAnalysisService,
        private CreativeAnalysisService $creativeAnalysisService,
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

                $opportunity = $this->conversionOpportunityService->analyze($job);

                $opportunityScore = MarketingScore::updateOrCreate(
                    ['analysis_job_id' => $job->id, 'module_key' => ConversionOpportunityService::MODULE_KEY],
                    [
                        'website_id' => $job->website_id,
                        'score' => $opportunity['score'],
                        'methodology' => 'heuristic',
                        'version' => $opportunity['version'],
                        'breakdown' => [
                            'label' => $opportunity['label'],
                            'methodology' => $opportunity['methodology'],
                            'opportunities' => array_map(fn (array $item) => Arr::except($item, 'raw'), $opportunity['opportunities']),
                            'high' => array_column($opportunity['high'], 'key'),
                            'medium' => array_column($opportunity['medium'], 'key'),
                            'low' => array_column($opportunity['low'], 'key'),
                        ],
                        'computed_at' => now(),
                    ],
                );

                PredictiveInsight::where('analysis_job_id', $job->id)->delete();

                foreach ($opportunity['opportunities'] as $item) {
                    unset($item['raw']);

                    PredictiveInsight::create([
                        ...collect($item)->except('key')->all(),
                        'opportunity_key' => $item['key'],
                        'website_id' => $job->website_id,
                        'analysis_job_id' => $job->id,
                    ]);
                }

                $lead = $this->leadReadinessService->analyze($job);

                $leadScore = MarketingScore::updateOrCreate(
                    ['analysis_job_id' => $job->id, 'module_key' => LeadReadinessService::MODULE_KEY],
                    [
                        'website_id' => $job->website_id,
                        'score' => $lead['score'],
                        'methodology' => 'heuristic',
                        'version' => $lead['version'],
                        'breakdown' => [
                            'label' => $lead['label'],
                            'methodology' => $lead['methodology'],
                            'summary' => $lead['summary'],
                            'forms' => collect($lead['forms'])->values()->all(),
                            'contact_methods' => $lead['contact_methods'],
                            'confidence' => $lead['confidence'],
                        ],
                        'computed_at' => now(),
                    ],
                );

                LeadInsight::where('analysis_job_id', $job->id)->delete();

                foreach ($lead['insights'] as $item) {
                    LeadInsight::create([...$item, 'website_id' => $job->website_id, 'analysis_job_id' => $job->id]);
                }

                $waste = $this->wastedSpendAnalysisService->analyze($job, $technical, $readiness);

                WasteFinding::where('analysis_job_id', $job->id)->delete();

                foreach ($waste['findings'] as $item) {
                    WasteFinding::create([...$item, 'website_id' => $job->website_id, 'analysis_job_id' => $job->id]);
                }

                $creative = $this->creativeAnalysisService->generate($job);

                CreativeInsight::where('analysis_job_id', $job->id)->delete();

                $creativeCount = $this->persistCreative($job, $creative);

                return [
                    'technical_score' => $audit->score,
                    'campaign_score' => $score->score,
                    'opportunity_score' => $opportunityScore->score,
                    'lead_score' => $leadScore->score,
                    'insights' => count($insights),
                    'findings' => count($waste['findings']),
                    'creative_items' => $creativeCount,
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

    private function persistCreative(AnalysisJob $job, array $creative): int
    {
        $count = 0;

        $map = [
            'google_headlines' => ['kind' => 'google_headline', 'channel' => 'google', 'field' => 'headline'],
            'google_descriptions' => ['kind' => 'google_description', 'channel' => 'google', 'field' => 'text'],
            'meta_primary_text' => ['kind' => 'meta_primary_text', 'channel' => 'meta', 'field' => 'text'],
            'meta_headlines' => ['kind' => 'meta_headline', 'channel' => 'meta', 'field' => 'headline'],
        ];

        foreach ($map as $groupKey => $meta) {
            foreach ($creative[$groupKey] ?? [] as $item) {
                CreativeInsight::create([
                    'website_id' => $job->website_id,
                    'analysis_job_id' => $job->id,
                    'kind' => $meta['kind'],
                    'channel' => $meta['channel'],
                    $meta['field'] => $item['text'],
                    'char_length' => $item['char_length'],
                ]);
                $count++;
            }
        }

        foreach ($creative['angles'] ?? [] as $text) {
            CreativeInsight::create([
                'website_id' => $job->website_id,
                'analysis_job_id' => $job->id,
                'kind' => 'messaging_angle',
                'text' => $text,
            ]);
            $count++;
        }

        if (array_key_exists('message_match', $creative)) {
            CreativeInsight::create([
                'website_id' => $job->website_id,
                'analysis_job_id' => $job->id,
                'kind' => 'message_match',
                'payload' => $creative['message_match'],
            ]);
            $count++;
        }

        return $count;
    }
}
