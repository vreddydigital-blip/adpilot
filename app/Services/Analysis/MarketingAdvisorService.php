<?php

namespace App\Services\Analysis;

use App\Models\AnalysisJob;
use App\Models\CampaignInsight;
use App\Models\LeadInsight;
use App\Models\MarketingScore;
use App\Models\PredictiveInsight;
use App\Models\TechnicalAudit;
use App\Models\WasteFinding;
use App\Services\Advisor\AiProvider;

class MarketingAdvisorService
{
    public const VERSION = 'v1.0';

    public const MODULE_KEY = 'marketing_advisor';

    private const SCORE_LABELS = [
        'technical_audit',
        'campaign_readiness',
        'conversion_opportunity',
        'lead_intelligence',
    ];

    public function __construct(private AiProvider $provider)
    {
    }

    public function advise(AnalysisJob $job): array
    {
        $scores = $this->collectScores($job);
        $items = $this->collectItems($job);
        $context = [
            'page_count' => $job->crawledPages()->count(),
            'scores' => $scores,
            'items' => $items,
        ];

        $output = $this->provider->recommend($context);

        return [
            'module_key' => self::MODULE_KEY,
            'version' => self::VERSION,
            'label' => 'AI Marketing Advisor',
            'engine' => $this->provider->name(),
            'engine_configured' => $this->provider->isConfigured(),
            'engine_note' => 'Deterministic rule engine running on stored crawl evidence and module results; it is not a large language model and its output is not a validated ML prediction.',
            'methodology' => 'Cross-module consolidation de-duplicated by topic and ranked by priority; built only from crawl evidence and heuristic module scores.',
            'summary' => $output['summary'],
            'scores' => $scores,
            'advisor_score' => $this->advisorScore($scores),
            'advisor_score_note' => '100 minus the mean of the known module scores. It is a simple aggregation of heuristic scores, not a marketing performance metric.',
            'priorities' => $this->priorityCounts($output['recommendations']),
            'disclaimer' => 'No advertising-platform or revenue data is used; recommendations describe conversion-readiness gaps only and never forecast spend, CPA, ROAS or revenue.',
            'recommendations' => $output['recommendations'],
        ];
    }

    private function collectScores(AnalysisJob $job): array
    {
        $scoreRows = MarketingScore::where('analysis_job_id', $job->id)
            ->whereIn('module_key', self::SCORE_LABELS)
            ->get()
            ->keyBy('module_key');

        $audit = TechnicalAudit::where('analysis_job_id', $job->id)->first();

        return [
            'technical_audit' => $audit?->score,
            'campaign_readiness' => $scoreRows->get('campaign_readiness')?->score,
            'conversion_opportunity' => $scoreRows->get('conversion_opportunity')?->score,
            'lead_intelligence' => $scoreRows->get('lead_intelligence')?->score,
            'confidence' => $scoreRows->get('campaign_readiness')?->breakdown['confidence'] ?? null,
        ];
    }

    private function collectItems(AnalysisJob $job): array
    {
        $items = [];

        foreach (WasteFinding::where('analysis_job_id', $job->id)->get() as $row) {
            $items[] = [
                'key' => $row->finding_key,
                'module' => 'wasted_spend',
                'priority' => $row->severity,
                'title' => $row->title,
                'evidence' => $row->evidence ?? [],
                'action' => $row->correction,
                'effort' => null,
                'impact' => $row->potential_impact,
                'confidence' => null,
            ];
        }

        foreach (CampaignInsight::where('analysis_job_id', $job->id)->get() as $row) {
            $items[] = [
                'key' => $row->module_key.'_'.$this->slug($row->title),
                'module' => $row->module_key,
                'priority' => $row->priority,
                'title' => $row->title,
                'evidence' => $row->evidence ?? [],
                'action' => $row->recommendation,
                'effort' => $row->effort,
                'impact' => null,
                'confidence' => $row->confidence,
            ];
        }

        foreach (PredictiveInsight::where('analysis_job_id', $job->id)->get() as $row) {
            $items[] = [
                'key' => $row->opportunity_key,
                'module' => 'conversion_opportunity',
                'priority' => $row->priority,
                'title' => $row->title,
                'evidence' => $row->evidence ?? [],
                'action' => $row->recommendation,
                'effort' => $row->effort,
                'impact' => $row->direction,
                'confidence' => $this->confidenceLabel($row->confidence),
            ];
        }

        foreach (LeadInsight::where('analysis_job_id', $job->id)->get() as $row) {
            $items[] = [
                'key' => $row->insight_key,
                'module' => 'lead_intelligence',
                'priority' => $row->priority,
                'title' => $row->title,
                'evidence' => $row->evidence ?? [],
                'action' => $row->recommendation,
                'effort' => $row->effort,
                'impact' => null,
                'confidence' => null,
            ];
        }

        foreach ($this->failedChecks($job) as $check) {
            $items[] = [
                'key' => 'technical_'.$check['key'],
                'module' => 'technical_audit',
                'priority' => $check['status'] === 'fail' ? 'high' : 'low',
                'title' => 'Technical: '.$check['label'],
                'evidence' => $check['evidence'] ?? [],
                'action' => $check['recommendation'],
                'effort' => null,
                'impact' => null,
                'confidence' => 'high',
            ];
        }

        return $items;
    }

    private function failedChecks(AnalysisJob $job): array
    {
        $audit = TechnicalAudit::where('analysis_job_id', $job->id)->first();

        if ($audit === null) {
            return [];
        }

        return array_values(array_filter(
            (array) $audit->checks,
            fn (array $check) => in_array($check['status'], ['fail', 'warn'], true) && filled($check['recommendation'] ?? null),
        ));
    }

    private function advisorScore(array $scores): ?int
    {
        $known = array_filter(array_intersect_key($scores, array_flip(self::SCORE_LABELS)), fn ($value) => $value !== null);

        if ($known === []) {
            return null;
        }

        return 100 - (int) round(array_sum($known) / count($known));
    }

    private function confidenceLabel($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value >= 70 ? 'high' : ($value >= 40 ? 'medium' : 'low');
    }

    private function priorityCounts(array $recommendations): array
    {
        $counts = ['high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($recommendations as $recommendation) {
            $counts[$recommendation['priority']] = ($counts[$recommendation['priority']] ?? 0) + 1;
        }

        return $counts;
    }

    private function slug(string $title): string
    {
        return preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($title));
    }
}
