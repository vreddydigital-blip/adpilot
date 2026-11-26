<?php

namespace App\Services\Reports;

use App\Models\AnalysisJob;
use App\Models\CampaignInsight;
use App\Models\LeadInsight;
use App\Models\MarketingScore;
use App\Models\PredictiveInsight;
use App\Models\TechnicalAudit;
use App\Models\WasteFinding;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\User;
use App\Models\Website;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\Factory as ViewFactory;

class ReportGenerationService
{
    public const VERSION = 'v1.0';

    public const FORMATS = ['pdf', 'csv'];

    public const SCORE_MODULES = [
        'campaign_readiness',
        'technical_audit',
        'conversion_opportunity',
        'lead_intelligence',
        'marketing_advisor',
    ];

    public const DISCLAIMER = 'All scores are heuristic, evidence-based evaluations derived from crawl data only. They are not validated ML predictions and this report never contains real advertising metrics (spend, CPC, CPA, conversion rate, revenue, ROAS).';

    public function __construct(private ViewFactory $view)
    {
    }

    public function generate(User $user, Website $website, AnalysisJob $job, string $format): Report
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw new \InvalidArgumentException('Unsupported report format: '.$format);
        }

        if ($job->status !== AnalysisJob::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Reports require a completed analysis job.');
        }

        $snapshot = $this->snapshot($job);

        $filename = 'adpilot-report-'.Str::uuid()->toString().'.'.$format;
        $path = 'reports/'.$filename;
        $content = $format === Report::FORMAT_PDF
            ? $this->renderPdf($snapshot)
            : $this->renderCsv($snapshot);

        Storage::disk('local')->put($path, $content);

        return Report::create([
            'user_id' => $user->id,
            'website_id' => $website->id,
            'analysis_job_id' => $job->id,
            'uuid' => Str::uuid()->toString(),
            'title' => 'AdPilot AI '.$format.' report - '.$this->reportLabel($website),
            'format' => $format,
            'status' => Report::STATUS_READY,
            'path' => $path,
            'filename' => $filename,
            'size_bytes' => strlen($content),
            'payload' => $snapshot,
            'generator' => $format === Report::FORMAT_PDF ? 'dompdf' : 'fputcsv',
            'version' => self::VERSION,
        ]);
    }

    public function ensureFile(Report $report): ?string
    {
        $path = $report->path;

        if ($path === null) {
            $report->update(['status' => 'expired', 'path' => null]);

            return null;
        }

        if (Storage::disk('local')->exists($path)) {
            return $path;
        }

        $snapshot = $report->payload;

        $job = $report->analysis_job_id !== null ? AnalysisJob::find($report->analysis_job_id) : null;

        if ($snapshot === null && $job !== null) {
            $snapshot = $this->snapshot($job);
        }

        if ($snapshot === null) {
            $report->update(['status' => 'expired', 'path' => null]);

            return null;
        }

        $content = $report->format === Report::FORMAT_PDF
            ? $this->renderPdf($snapshot)
            : $this->renderCsv($snapshot);

        Storage::disk('local')->put($path, $content);
        $report->update(['size_bytes' => strlen($content), 'status' => Report::STATUS_READY]);

        return $path;
    }

    public function snapshot(AnalysisJob $job): array
    {
        $website = $job->website;

        $scores = $this->scoresFor($job->id);

        $findings = collect()
            ->merge($this->campaignRows($job->id))
            ->merge($this->predictiveRows($job->id))
            ->merge($this->leadRows($job->id))
            ->merge($this->wasteRows($job->id));

        $recommendations = Recommendation::where('analysis_job_id', $job->id)
            ->orderBy('position')
            ->get()
            ->map(fn ($row) => [
                'position' => $row->position,
                'priority' => $row->priority,
                'title' => $row->title,
                'action' => $row->action,
                'modules' => implode(', ', $row->module_source ?? []),
            ]);

        $campaignCriteria = [];

        if (isset($scores['campaign_readiness']['breakdown']['criteria'])) {
            foreach ($scores['campaign_readiness']['breakdown']['criteria'] as $criterion) {
                $campaignCriteria[] = [
                    'label' => $criterion['label'] ?? ($criterion['key'] ?? 'criterion'),
                    'score' => $criterion['score'] ?? null,
                    'weight' => $criterion['weight'] ?? null,
                ];
            }
        }

        return [
            'generated_at' => now()->toISOString(),
            'version' => self::VERSION,
            'website' => [
                'url' => $website->url,
                'host' => $website->host,
                'label' => $website->label,
            ],
            'job' => [
                'uuid' => $job->uuid,
                'finished_at' => $job->finished_at?->toISOString(),
                'pages' => $job->crawledPages()->count(),
                'errors' => $job->crawlErrors()->count(),
            ],
            'scores' => collect($scores)->map(fn (array $row) => [
                'module' => $row['module_key'],
                'score' => $row['score'],
                'methodology' => $row['methodology'],
                'version' => $row['version'],
            ])->values()->all(),
            'technical_checks' => array_map(fn (array $check) => [
                'label' => $check['label'],
                'status' => $check['status'],
                'value' => $check['value'] ?? null,
            ], $this->technicalChecks($job->id)),
            'campaign_criteria' => $campaignCriteria,
            'findings' => $findings->values()->all(),
            'recommendations' => $recommendations->all(),
            'disclaimer' => self::DISCLAIMER,
        ];
    }

    public function history(Website $website): array
    {
        $jobs = $website->analysisJobs()
            ->where('status', AnalysisJob::STATUS_COMPLETED)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->limit(12)
            ->get();

        if ($jobs->isEmpty()) {
            return [];
        }

        $scoresByJob = MarketingScore::whereIn('analysis_job_id', $jobs->modelKeys())
            ->get()
            ->groupBy('analysis_job_id');

        $auditsByJob = TechnicalAudit::whereIn('analysis_job_id', $jobs->modelKeys())
            ->get()
            ->keyBy('analysis_job_id');

        return $jobs->reverse()->values()->map(fn (AnalysisJob $job) => [
            'uuid' => $job->uuid,
            'finished_at' => $job->finished_at->toISOString(),
            'scores' => collect(self::SCORE_MODULES)
                ->mapWithKeys(function (string $module) use ($scoresByJob, $auditsByJob, $job) {
                    if ($module === 'technical_audit') {
                        return [$module => $auditsByJob->get($job->id)?->score];
                    }

                    $score = $scoresByJob->get($job->id, collect())->firstWhere('module_key', $module);

                    return [$module => $score?->score];
                })->all(),
        ])->all();
    }

    private function renderPdf(array $snapshot): string
    {
        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($this->view->make('reports.pdf', ['snapshot' => $snapshot])->render());
        $dompdf->setPaper('a4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function renderCsv(array $snapshot): string
    {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, ['AdPilot AI report']);
        fputcsv($stream, ['website', $snapshot['website']['url']]);
        fputcsv($stream, ['analysis_job', $snapshot['job']['uuid']]);
        fputcsv($stream, ['finished_at', $snapshot['job']['finished_at']]);
        fputcsv($stream, ['crawled_pages', $snapshot['job']['pages']]);
        fputcsv($stream, ['crawl_errors', $snapshot['job']['errors']]);
        fputcsv($stream, ['generated_at', $snapshot['generated_at']]);
        fputcsv($stream, ['report_version', $snapshot['version']]);

        fputcsv($stream, []);
        fputcsv($stream, ['SECTION', 'module scores (heuristic)']);
        fputcsv($stream, ['module', 'score', 'methodology', 'version']);

        foreach ($snapshot['scores'] as $score) {
            fputcsv($stream, [$score['module'], $score['score'], $score['methodology'], $score['version']]);
        }

        fputcsv($stream, []);
        fputcsv($stream, ['SECTION', 'campaign readiness criteria']);
        fputcsv($stream, ['criterion', 'score', 'weight']);

        foreach ($snapshot['campaign_criteria'] as $criterion) {
            fputcsv($stream, [$criterion['label'], $criterion['score'], $criterion['weight']]);
        }

        fputcsv($stream, []);
        fputcsv($stream, ['SECTION', 'technical audit checks']);
        fputcsv($stream, ['check', 'status', 'value']);

        foreach ($snapshot['technical_checks'] as $check) {
            fputcsv($stream, [$check['label'], $check['status'], $check['value']]);
        }

        fputcsv($stream, []);
        fputcsv($stream, ['SECTION', 'advisor recommendations']);
        fputcsv($stream, ['position', 'priority', 'recommendation', 'action', 'source_modules']);

        foreach ($snapshot['recommendations'] as $item) {
            fputcsv($stream, [$item['position'], $item['priority'], $item['title'], $item['action'], $item['modules']]);
        }

        fputcsv($stream, []);
        fputcsv($stream, ['SECTION', 'findings and insights']);
        fputcsv($stream, ['module', 'priority', 'finding', 'recommendation']);

        foreach ($snapshot['findings'] as $item) {
            fputcsv($stream, [$item['module'], $item['priority'], $item['title'], $item['recommendation']]);
        }

        fputcsv($stream, []);
        fputcsv($stream, ['DISCLAIMER', $snapshot['disclaimer']]);

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    private function scoresFor(int $jobId): array
    {
        $rows = MarketingScore::where('analysis_job_id', $jobId)->get();

        $scores = [];

        if ($audit = TechnicalAudit::where('analysis_job_id', $jobId)->first()) {
            $scores['technical_audit'] = [
                'module_key' => 'technical_audit',
                'score' => $audit->score,
                'methodology' => 'heuristic audit (8 evidence checks)',
                'version' => 'v1.0',
                'breakdown' => [],
            ];
        }

        foreach ($rows as $row) {
            $scores[$row->module_key] = $row->toArray();
        }

        return $scores;
    }

    private function technicalChecks(int $jobId): array
    {
        $audit = TechnicalAudit::where('analysis_job_id', $jobId)->first();

        if ($audit === null) {
            return [];
        }

        return (array) $audit->checks;
    }

    private function campaignRows(int $jobId): Collection
    {
        return CampaignInsight::where('analysis_job_id', $jobId)->get()->map(fn ($row) => [
            'module' => $row->module_key,
            'priority' => $row->priority,
            'title' => $row->title,
            'recommendation' => $row->recommendation,
        ]);
    }

    private function predictiveRows(int $jobId): Collection
    {
        return PredictiveInsight::where('analysis_job_id', $jobId)
            ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
            ->get()
            ->map(fn ($row) => [
                'module' => 'conversion_opportunity',
                'priority' => $row->priority,
                'title' => $row->title,
                'recommendation' => $row->recommendation,
            ]);
    }

    private function leadRows(int $jobId): Collection
    {
        return LeadInsight::where('analysis_job_id', $jobId)->get()->map(fn ($row) => [
            'module' => 'lead_intelligence',
            'priority' => $row->priority,
            'title' => $row->title,
            'recommendation' => $row->recommendation,
        ]);
    }

    private function wasteRows(int $jobId): Collection
    {
        return WasteFinding::where('analysis_job_id', $jobId)
            ->orderByRaw("case severity when 'high' then 0 when 'medium' then 1 else 2 end")
            ->get()
            ->map(fn ($row) => [
                'module' => 'wasted_spend',
                'priority' => $row->severity,
                'title' => $row->title,
                'recommendation' => $row->correction,
            ]);
    }

    private function reportLabel(Website $website): string
    {
        return $website->label ?: $website->host;
    }
}
