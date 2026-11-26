<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateReportRequest;
use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\Website;
use App\Services\Reports\ReportGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReportApiController extends Controller
{
    public function __construct(private ReportGenerationService $service)
    {
    }

    public function index(Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        $reports = Report::where('website_id', $website->id)
            ->where('user_id', auth()->id())
            ->with('job')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return response()->json([
            'website_id' => $website->id,
            'reports' => $reports->map(fn (Report $report) => $this->meta($report))->values(),
        ]);
    }

    public function store(GenerateReportRequest $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);
        $this->authorize('analyze', $website);

        $job = $website->analysisJobs()->where('status', AnalysisJob::STATUS_COMPLETED)->latest()->first();

        if ($job === null) {
            return response()->json([
                'message' => 'No completed analysis yet. Reports require a completed crawl; run an analysis first.',
            ], 422);
        }

        try {
            $report = $this->service->generate(auth()->user(), $website, $job, $request->validated('format'));
        } catch (Throwable $e) {
            AuditLog::record('report.generation_failed', auth()->id(), $website, [
                'format' => $request->validated('format'),
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);

            return response()->json([
                'message' => 'Report generation failed: '.mb_substr($e->getMessage(), 0, 200),
            ], 500);
        }

        return response()->json([
            'report' => $this->meta($report),
            'message' => ucfirst($report->format).' report generated from analysis '.$job->uuid.'.',
        ], 201);
    }

    public function show(Report $report): JsonResponse
    {
        $this->authorizeOwner($report);

        return response()->json(['report' => $this->meta($report)]);
    }

    public function download(Report $report): Response
    {
        $this->authorizeOwner($report);

        $path = $this->service->ensureFile($report);

        if ($path === null) {
            return response()->json([
                'message' => 'This report file is no longer stored (ephemeral filesystem). Regenerate a report from the Reports screen.',
            ], 404);
        }

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => $report->format === Report::FORMAT_PDF
                ? 'application/pdf'
                : 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$report->filename.'"',
        ]);
    }

    private function authorizeOwner(Report $report): void
    {
        abort_unless($report->user_id === (int) auth()->id(), 403, 'You do not own this report.');

        $website = $report->website;

        if ($website !== null) {
            $this->authorize('view', $website);
        }
    }

    private function meta(Report $report): array
    {
        return [
            'id' => $report->id,
            'uuid' => $report->uuid,
            'title' => $report->title,
            'format' => $report->format,
            'status' => $report->status,
            'size_bytes' => $report->size_bytes,
            'analysis_job' => $report->analysis_job_id !== null
                ? ['uuid' => $report->job?->uuid, 'finished_at' => $report->job?->finished_at?->toISOString()]
                : null,
            'generator' => $report->generator,
            'version' => $report->version,
            'download_url' => route('api.reports.download', $report),
            'created_at' => $report->created_at->toISOString(),
        ];
    }
}
