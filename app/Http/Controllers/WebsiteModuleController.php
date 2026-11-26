<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetScenarioRequest;
use App\Models\BudgetScenario;
use App\Models\CreativeInsight;
use App\Models\LeadInsight;
use App\Models\MarketingScore;
use App\Models\PredictiveInsight;
use App\Http\Requests\GenerateReportRequest;
use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\WasteFinding;
use App\Models\Website;
use App\Services\Analysis\BudgetCalculationService;
use App\Services\Reports\ReportGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class WebsiteModuleController extends Controller
{
    public function predictive(Website $website): View
    {
        $this->authorize('view', $website);

        $data = $this->moduleData($website, 'conversion_opportunity');

        return view('websites.modules.predictive', [
            'website' => $website,
            'job' => $data['job'],
            'score' => $data['score'],
            'opportunities' => $data['job'] === null ? collect() : PredictiveInsight::where('analysis_job_id', $data['job']->id)
                ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
                ->orderByDesc('score')
                ->get(),
        ]);
    }

    public function wastedSpend(Website $website): View
    {
        $this->authorize('view', $website);

        $data = $this->moduleData($website);

        return view('websites.modules.wasted-spend', [
            'website' => $website,
            'job' => $data['job'],
            'findings' => $data['job'] === null ? collect() : WasteFinding::where('analysis_job_id', $data['job']->id)
                ->orderByRaw("case severity when 'high' then 0 when 'medium' then 1 else 2 end")
                ->get(),
        ]);
    }

    public function lead(Website $website): View
    {
        $this->authorize('view', $website);

        $data = $this->moduleData($website, 'lead_intelligence');

        return view('websites.modules.lead', [
            'website' => $website,
            'job' => $data['job'],
            'score' => $data['score'],
            'insights' => $data['job'] === null ? collect() : LeadInsight::where('analysis_job_id', $data['job']->id)
                ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
                ->get(),
        ]);
    }

    public function creative(Website $website): View
    {
        $this->authorize('view', $website);

        $data = $this->moduleData($website);

        $items = $data['job'] === null
            ? collect()
            : CreativeInsight::where('analysis_job_id', $data['job']->id)->orderBy('id')->get();

        return view('websites.modules.creative', [
            'website' => $website,
            'job' => $data['job'],
            'googleHeadlines' => $items->where('kind', 'google_headline')->values(),
            'googleDescriptions' => $items->where('kind', 'google_description')->values(),
            'metaHeadlines' => $items->where('kind', 'meta_headline')->values(),
            'metaPrimary' => $items->where('kind', 'meta_primary_text')->values(),
            'angles' => $items->where('kind', 'messaging_angle')->values(),
            'messageMatch' => $items->firstWhere('kind', 'message_match')?->payload,
        ]);
    }

    public function advisor(Website $website): View
    {
        $this->authorize('view', $website);

        $data = $this->moduleData($website, 'marketing_advisor');

        $recommendations = $data['job'] === null
            ? collect()
            : Recommendation::where('analysis_job_id', $data['job']->id)
                ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
                ->orderBy('position')
                ->get();

        return view('websites.modules.advisor', [
            'website' => $website,
            'job' => $data['job'],
            'score' => $data['score'],
            'recommendations' => $recommendations,
        ]);
    }

    public function budget(Website $website): View
    {
        $this->authorize('view', $website);

        $scenarios = $website->budgetScenarios()
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return view('websites.modules.budget', [
            'website' => $website,
            'channels' => BudgetCalculationService::CHANNELS,
            'scenarios' => $scenarios,
            'defaults' => [
                'budget' => 3000,
                'cpc' => 1.5,
                'lead_rate' => 5,
                'conversion_rate' => 10,
                'average_order_value' => 200,
                'gross_margin' => 40,
                'target_cpa' => null,
                'channel' => 'all',
            ],
        ]);
    }

    public function reports(Website $website, ReportGenerationService $service): View
    {
        $this->authorize('view', $website);

        $job = $this->latestCompletedJob($website);

        return view('websites.modules.reports', [
            'website' => $website,
            'job' => $job,
            'reports' => Report::where('website_id', $website->id)
                ->where('user_id', auth()->id())
                ->orderByDesc('created_at')
                ->limit(25)
                ->get(),
            'history' => $job === null ? [] : $service->history($website),
        ]);
    }

    public function storeReport(GenerateReportRequest $request, Website $website): RedirectResponse
    {
        $this->authorize('analyze', $website);

        $service = app(ReportGenerationService::class);
        $job = $this->latestCompletedJob($website);

        if ($job === null) {
            return back()->with('error', 'No completed analysis yet. Reports require a completed crawl; run an analysis first.');
        }

        try {
            $report = $service->generate(auth()->user(), $website, $job, $request->validated('format'));
        } catch (Throwable $e) {
            AuditLog::record('report.generation_failed', auth()->id(), $website, [
                'format' => $request->validated('format'),
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);

            return back()->with('error', 'Report generation failed: '.mb_substr($e->getMessage(), 0, 200));
        }

        return back()->with('success', ucfirst($report->format).' report generated from analysis '.$job->uuid.'.');
    }

    public function downloadReport(Website $website, Report $report): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $website);
        abort_unless($report->website_id === $website->id && $report->user_id === (int) auth()->id(), 403);

        $service = app(ReportGenerationService::class);
        $path = $service->ensureFile($report);

        if ($path === null) {
            return back()->with('error', 'This report file is no longer stored (ephemeral filesystem). Please generate a new report.');
        }

        return Storage::disk('local')->download($path, $report->filename);
    }

    private function latestCompletedJob(Website $website): ?AnalysisJob
    {
        return $website->analysisJobs()
            ->where('status', AnalysisJob::STATUS_COMPLETED)
            ->latest()
            ->first();
    }

    public function storeScenario(StoreBudgetScenarioRequest $request, Website $website): RedirectResponse
    {
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

        $sourceInputs = [
            'budget' => (float) $validated['budget'],
            'cpc' => (float) $validated['cpc'],
            'lead_rate' => (float) $validated['lead_rate'],
            'conversion_rate' => (float) $validated['conversion_rate'],
            'average_order_value' => (float) $validated['average_order_value'],
            'gross_margin' => (float) $validated['gross_margin'],
            'target_cpa' => isset($validated['target_cpa']) ? (float) $validated['target_cpa'] : null,
        ];

        $results = [
            'source_inputs' => $sourceInputs,
            'channel' => $channelKey,
            'scenarios' => $service->scenarios(
                (float) $validated['budget'],
                $cpc,
                $leadRate,
                $conversionRate,
                (float) $validated['average_order_value'],
                (float) $validated['gross_margin'],
                $sourceInputs['target_cpa'],
            ),
        ];

        BudgetScenario::create([
            'website_id' => $website->id,
            'user_id' => (int) $request->user()->getAuthIdentifier(),
            'label' => $validated['label'] ?? ucfirst($channelKey).' scenario',
            'channel' => $channelKey,
            'inputs' => $sourceInputs,
            'results' => $results,
        ]);

        return redirect()
            ->route('websites.budget', $website)
            ->with('status', 'Budget scenario saved. All figures are simulations from your stated assumptions, not actual campaign performance.');
    }

    public function destroyScenario(Website $website, BudgetScenario $scenario): RedirectResponse
    {
        abort_unless($scenario->website_id === $website->id && $scenario->user_id === (int) auth()->id(), 403);

        $scenario->delete();

        return redirect()->route('websites.budget', $website)->with('status', 'Budget scenario deleted.');
    }

    private function moduleData(Website $website, ?string $moduleKey = null): array
    {
        $job = $website->analysisJobs()->where('status', 'completed')->latest()->first();

        $score = ($job !== null && $moduleKey !== null)
            ? MarketingScore::where('analysis_job_id', $job->id)->where('module_key', $moduleKey)->first()
            : null;

        return ['job' => $job, 'score' => $score];
    }
}
