<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebsiteRequest;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Website;
use App\Models\CampaignInsight;
use App\Models\MarketingScore;
use App\Models\TechnicalAudit;
use App\Services\Analysis\AnalysisJobService;
use App\Services\Security\UrlValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function __construct(
        private UrlValidator $urlValidator,
        private AnalysisJobService $analysisJobService,
    ) {
    }

    public function store(StoreWebsiteRequest $request): RedirectResponse
    {
        $project = Project::findOrFail($request->integer('project_id'));
        $this->authorize('view', $project);

        $validation = $this->urlValidator->validate($request->input('url'));

        if (! $validation['valid']) {
            return back()
                ->withErrors(['url' => $validation['error']])
                ->withInput();
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

        AuditLog::record('website.created', auth()->id(), $website, ['host' => $website->host], $request->ip(), $request->userAgent());

        return redirect()
            ->route('websites.show', $website)
            ->with('status', $website->wasRecentlyCreated
                ? 'Website added to project.'
                : 'This website already exists in the project.');
    }

    public function show(Website $website): View
    {
        $this->authorize('view', $website);

        $website->load(['project']);

        $jobs = $website->analysisJobs()
            ->latest('id')
            ->take(20)
            ->get();

        $completedJob = $jobs->firstWhere('status', 'completed');
        $technicalAudit = $completedJob ? TechnicalAudit::where('analysis_job_id', $completedJob->id)->first() : null;
        $readinessScore = $completedJob
            ? MarketingScore::where('analysis_job_id', $completedJob->id)->where('module_key', 'campaign_readiness')->first()
            : null;
        $insights = $completedJob
            ? CampaignInsight::where('analysis_job_id', $completedJob->id)
                ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
                ->orderBy('id')
                ->get()
            : collect();

        return view('websites.show', [
            'website' => $website,
            'jobs' => $jobs,
            'technicalAudit' => $technicalAudit,
            'readinessScore' => $readinessScore,
            'insights' => $insights,
        ]);
    }

    public function destroy(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('delete', $website);

        $projectId = $website->project_id;
        $website->delete();

        AuditLog::record('website.deleted', auth()->id(), null, ['website_id' => $website->id, 'host' => $website->host], $request->ip(), $request->userAgent());

        return redirect()
            ->route('projects.show', $projectId)
            ->with('status', 'Website removed.');
    }

    public function analyze(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('analyze', $website);

        [$job, $created, $dispatch] = array_values($this->analysisJobService->startAnalysis($website, $request->user(), $request));

        if (! $created) {
            return back()->with('status', 'An analysis is already running for this website.');
        }

        $message = $dispatch['dispatched']
            ? 'Analysis dispatched to the crawler runner.'
            : 'Analysis job created. No crawler runner is configured, so the job stays pending until a runner picks it up (use analysis:run-local locally).';

        return back()->with('status', $message);
    }
}
