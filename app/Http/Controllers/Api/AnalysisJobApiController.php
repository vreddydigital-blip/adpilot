<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnalysisJobResource;
use App\Models\AnalysisJob;
use Illuminate\Http\Request;

class AnalysisJobApiController extends Controller
{
    public function show(Request $request, AnalysisJob $analysisJob): AnalysisJobResource
    {
        $this->authorize('view', $analysisJob);

        return new AnalysisJobResource($analysisJob);
    }
}
