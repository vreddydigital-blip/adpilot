<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteRequest;
use App\Http\Resources\AnalysisJobResource;
use App\Http\Resources\WebsiteResource;
use App\Models\Project;
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
            ->additional(['job_created' => $result['created']])
            ->response()
            ->setStatusCode($result['created'] ? 202 : 200);
    }
}
