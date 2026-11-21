<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisJob;
use App\Services\Crawler\CallbackVerifier;
use App\Services\Crawler\CrawlerCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrawlerCallbackController extends Controller
{
    public function __construct(
        private CallbackVerifier $verifier,
        private CrawlerCallbackService $callbackService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $verification = $this->verifier->verify($request);

        if (! $verification['ok']) {
            $this->verifier->auditRejection($request, $verification);

            return response()->json(['message' => $verification['error']], 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Callback body must be valid JSON.'], 422);
        }

        $jobId = $payload['job_id'] ?? null;

        if (! is_string($jobId) || $jobId === '') {
            return response()->json(['message' => 'Callback is missing the job identifier.'], 422);
        }

        $job = AnalysisJob::with('website')->where('uuid', $jobId)->first();

        if ($job === null) {
            return response()->json(['message' => 'Unknown analysis job.'], 404);
        }

        $result = $this->callbackService->process($job, $payload);

        return response()->json([
            'outcome' => $result['outcome'],
            'message' => $result['message'],
        ], $result['status']);
    }
}
