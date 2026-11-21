<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteRequest;
use App\Http\Resources\AnalysisJobResource;
use App\Http\Resources\WebsiteResource;
use App\Models\CampaignInsight;
use App\Models\MarketingScore;
use App\Models\Project;
use App\Models\TechnicalAudit;
use App\Models\Website;
use App\Services\Analysis\AnalysisJobService;
use App\Services\Security\UrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteApiController extends Controller
{
    public function __construct(
        private UrlValidator $urlValidator,
        private AnalysisJobService $analysisJobService,
    ) {
    }

    public function store(StoreWebsiteRequest $request): JsonResponse
    {
        $project = Project::findOrFail($request->integer('project_id'));
        $this->authorize('view', $project);

        $validation = $this->urlValidator->validate($request->input('url'));

        if (! $validation['valid']) {
            return response()->json([
                'message' => $validation['error'],
                'errors' => ['url' => [$validation['error']]],
            ], 422);
        }

        $website = $project->websites()->firstOrCreate(
            ['url_hash' => $validation['url_hash']],
            [
                'user_id' => $project->user_id,
                'url' => trim($request->input('url')),
                'normalized_url' => $validation['normalized_url'],
                'host' => $validation['host'],
                'label' => $request->input('label'),
            ],
        );

        return (new WebsiteResource($website))
            ->response()
            ->setStatusCode($website->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, Website $website): WebsiteResource
    {
        $this->authorize('view', $website);

        return new WebsiteResource($website);
    }

    public function analyze(Request $request, Website $website): JsonResponse
    {
        $this->authorize('analyze', $website);

        $result = $this->analysisJobService->startAnalysis($website, $request->user(), $request);

        return (new AnalysisJobResource($result['job']))
            ->additional(['job_created' => $result['created'], 'dispatch' => $result['dispatch']])
            ->response()
            ->setStatusCode($result['created'] ? 202 : 200);
    }

    public function campaignIntelligence(Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        $job = $website->analysisJobs()->where('status', 'completed')->latest()->first();

        if ($job === null) {
            return response()->json([
                'website_id' => $website->id,
                'data' => null,
                'message' => 'No completed analysis yet. Run an analysis first; results are unavailable until a crawl has completed.',
            ]);
        }

        $audit = TechnicalAudit::where('analysis_job_id', $job->id)->first();
        $score = MarketingScore::where('analysis_job_id', $job->id)->where('module_key', 'campaign_readiness')->first();
        $insights = CampaignInsight::where('analysis_job_id', $job->id)
            ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
            ->orderBy('id')
            ->get();

        return response()->json([
            'website_id' => $website->id,
            'analysis_job' => ['uuid' => $job->uuid, 'finished_at' => $job->finished_at?->toISOString()],
            'campaign_readiness' => $score === null ? null : [
                'score' => $score->score,
                'version' => $score->version,
                'methodology' => $score->methodology,
                'computed_at' => $score->computed_at?->toISOString(),
                'breakdown' => $score->breakdown,
            ],
            'technical_audit' => $audit === null ? null : [
                'score' => $audit->score,
                'version' => $audit->version,
                'checks' => $audit->checks,
            ],
            'insights' => $insights->map(fn (CampaignInsight $insight) => [
                'module_key' => $insight->module_key,
                'priority' => $insight->priority,
                'title' => $insight->title,
                'evidence' => $insight->evidence,
                'recommendation' => $insight->recommendation,
                'effort' => $insight->effort,
                'direction' => $insight->direction,
                'confidence' => $insight->confidence,
            ]),
        ]);
    }
}
