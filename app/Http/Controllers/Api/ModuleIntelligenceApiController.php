<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBudgetScenarioRequest;
use App\Models\BudgetScenario;
use App\Models\CreativeInsight;
use App\Models\LeadInsight;
use App\Models\MarketingScore;
use App\Models\PredictiveInsight;
use App\Models\Recommendation;
use App\Models\WasteFinding;
use App\Models\Website;
use App\Services\Analysis\BudgetCalculationService;
use App\Services\Analysis\ConversionOpportunityService;
use App\Services\Analysis\CreativeAnalysisService;
use App\Services\Analysis\LeadReadinessService;
use App\Services\Analysis\MarketingAdvisorService;
use App\Services\Analysis\WastedSpendAnalysisService;
use Illuminate\Http\JsonResponse;

class ModuleIntelligenceApiController extends Controller
{
    private function payload(Website $website, string $moduleKey): JsonResponse
    {
        $this->authorize('view', $website);

        $job = $website->analysisJobs()->where('status', 'completed')->latest()->first();

        if ($job === null) {
            return response()->json([
                'website_id' => $website->id,
                'module' => $moduleKey,
                'analysis_job' => null,
                'data' => null,
                'message' => 'No completed analysis yet. Run an analysis first; '.$moduleKey.' results are unavailable until a crawl has completed.',
            ]);
        }

        return response()->json([
            'website_id' => $website->id,
            'module' => $moduleKey,
            'analysis_job' => ['uuid' => $job->uuid, 'finished_at' => $job->finished_at?->toISOString()],
            'data' => null,
        ]);
    }

    private function scoreRow(int $jobId, string $moduleKey): ?MarketingScore
    {
        return MarketingScore::where('analysis_job_id', $jobId)->where('module_key', $moduleKey)->first();
    }

    public function predictiveAnalytics(Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        $job = $website->analysisJobs()->where('status', 'completed')->latest()->first();

        if ($job === null) {
            return $this->payload($website, ConversionOpportunityService::MODULE_KEY);
        }

        $score = $this->scoreRow($job->id, ConversionOpportunityService::MODULE_KEY);
        $opportunities = PredictiveInsight::where('analysis_job_id', $job->id)
            ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
            ->orderByDesc('score')
            ->get();

        return response()->json([
            'website_id' => $website->id,
            'module' => ConversionOpportunityService::MODULE_KEY,
            'analysis_job' => ['uuid' => $job->uuid, 'finished_at' => $job->finished_at?->toISOString()],
            'data' => [
                'score' => $score?->score,
                'version' => $score?->version,
                'breakdown' => $score?->breakdown,
                'opportunities' => $opportunities,
            ],
        ]);
    }

    public function leadIntelligence(Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        $job = $website->analysisJobs()->where('status', 'completed')->latest()->first();

        if ($job === null) {
            return $this->payload($website, LeadReadinessService::MODULE_KEY);
        }

        $score = $this->scoreRow($job->id, LeadReadinessService::MODULE_KEY);
        $insights = LeadInsight::where('analysis_job_id', $job->id)
            ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
            ->get();

        return response()->json([
            'website_id' => $website->id,
            'module' => LeadReadinessService::MODULE_KEY,
            'analysis_job' => ['uuid' => $job->uuid, 'finished_at' => $job->finished_at?->toISOString()],
            'data' => [
                'score' => $score?->score,
                'version' => $score?->version,
                'breakdown' => $score?->breakdown,
                'insights' => $insights,
            ],
        ]);
    }

    public function wastedSpend(Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        $job = $website->analysisJobs()->where('status', 'completed')->latest()->first();

        if ($job === null) {
            return $this->payload($website, WastedSpendAnalysisService::MODULE_KEY);
        }

        $findings = WasteFinding::where('analysis_job_id', $job->id)
            ->orderByRaw("case severity when 'high' then 0 when 'medium' then 1 else 2 end")
            ->get();

        return response()->json([
            'website_id' => $website->id,
            'module' => WastedSpendAnalysisService::MODULE_KEY,
            'analysis_job' => ['uuid' => $job->uuid, 'finished_at' => $job->finished_at?->toISOString()],
            'data' => [
                'findings' => $findings,
                'disclaimer' => 'These findings are website conversion-leakage indicators only. Actual wasted advertising spend cannot be identified without advertising cost and conversion data (integrations, future phase).',
            ],
        ]);
    }

    public function creativeIntelligence(Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        $job = $website->analysisJobs()->where('status', 'completed')->latest()->first();

        if ($job === null) {
            return $this->payload($website, CreativeAnalysisService::MODULE_KEY);
        }

        $items = CreativeInsight::where('analysis_job_id', $job->id)->orderBy('id')->get();

        $messageMatch = $items->firstWhere('kind', 'message_match')?->payload;

        return response()->json([
            'website_id' => $website->id,
            'module' => CreativeAnalysisService::MODULE_KEY,
            'analysis_job' => ['uuid' => $job->uuid, 'finished_at' => $job->finished_at?->toISOString()],
            'data' => [
                'google_headlines' => $items->where('kind', 'google_headline')->values(),
                'google_descriptions' => $items->where('kind', 'google_description')->values(),
                'meta_headlines' => $items->where('kind', 'meta_headline')->values(),
                'meta_primary_text' => $items->where('kind', 'meta_primary_text')->values(),
                'messaging_angles' => $items->where('kind', 'messaging_angle')->values(),
                'message_match' => $messageMatch,
                'disclaimer' => 'Deterministic templates assembled from your website\'s own titles, headings, descriptions and CTA labels. No CTR or conversion predictions are made.',
            ],
        ]);
    }

    public function marketingAdvisor(Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        $job = $website->analysisJobs()->where('status', 'completed')->latest()->first();

        if ($job === null) {
            return $this->payload($website, MarketingAdvisorService::MODULE_KEY);
        }

        $score = $this->scoreRow($job->id, MarketingAdvisorService::MODULE_KEY);
        $recommendations = Recommendation::where('analysis_job_id', $job->id)
            ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
            ->orderBy('position')
            ->get();

        return response()->json([
            'website_id' => $website->id,
            'module' => MarketingAdvisorService::MODULE_KEY,
            'analysis_job' => ['uuid' => $job->uuid, 'finished_at' => $job->finished_at?->toISOString()],
            'data' => [
                'score' => $score?->score,
                'version' => $score?->version,
                'breakdown' => $score?->breakdown,
                'recommendations' => $recommendations,
            ],
        ]);
    }

    public function channelFactors(): JsonResponse
    {
        return response()->json([
            'channels' => collect(BudgetCalculationService::CHANNELS)
                ->map(fn (array $meta, string $key) => [
                    'channel' => $key,
                    'label' => $meta['label'],
                    'cpc_factor' => $meta['cpc_factor'],
                    'rate_factor' => $meta['rate_factor'],
                ])->values(),
            'disclaimer' => 'Static assumption factors for planning only. Real CPCs and conversion rates must come from advertising integrations (future phase).',
        ]);
    }

    public function budgetScenariosIndex(Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        $scenarios = $website->budgetScenarios()->where('user_id', $this->userId())->orderByDesc('created_at')->limit(25)->get();

        return response()->json([
            'website_id' => $website->id,
            'module' => 'budget_intelligence',
            'scenarios' => $scenarios,
        ]);
    }

    public function budgetScenariosStore(StoreBudgetScenarioRequest $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);
        $this->authorize('analyze', $website);

        $validated = $request->validated();
        $channelKey = $validated['channel'] ?? 'all';
        $service = app(BudgetCalculationService::class);

        $cpc = (float) $validated['cpc'];
        $leadRate = (float) $validated['lead_rate'];
        $conversionRate = (float) $validated['conversion_rate'];

        if ($channelKey !== 'all') {
            $meta = BudgetCalculationService::CHANNELS[$channelKey];
            $cpc *= $meta['cpc_factor'];
            $leadRate *= $meta['rate_factor'];
            $conversionRate *= $meta['rate_factor'];
        }

        $results = [
            'source_inputs' => [
                'budget' => (float) $validated['budget'],
                'cpc' => (float) $validated['cpc'],
                'lead_rate' => (float) $validated['lead_rate'],
                'conversion_rate' => (float) $validated['conversion_rate'],
                'average_order_value' => (float) $validated['average_order_value'],
                'gross_margin' => (float) $validated['gross_margin'],
                'target_cpa' => isset($validated['target_cpa']) ? (float) $validated['target_cpa'] : null,
            ],
            'channel' => $channelKey,
            'scenarios' => $service->scenarios(
                (float) $validated['budget'],
                $cpc,
                $leadRate,
                $conversionRate,
                (float) $validated['average_order_value'],
                (float) $validated['gross_margin'],
                isset($validated['target_cpa']) ? (float) $validated['target_cpa'] : null,
            ),
        ];

        $scenario = BudgetScenario::create([
            'website_id' => $website->id,
            'user_id' => $this->userId(),
            'label' => $validated['label'] ?? ucfirst($channelKey).' scenario',
            'channel' => $channelKey,
            'inputs' => $results['source_inputs'],
            'results' => $results,
        ]);

        return response()->json([
            'website_id' => $website->id,
            'scenario_id' => $scenario->id,
            'label' => $scenario->label,
            'channel' => $scenario->channel,
            'results' => $results,
            'disclaimer' => 'Deterministic simulation from your stated assumptions - not actual campaign performance.',
        ], 201);
    }

    private function userId(): int
    {
        return (int) request()->user()->getAuthIdentifier();
    }
}
