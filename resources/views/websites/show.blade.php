<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $website->label ?: $website->host }}
        </h2>
        <p class="text-sm text-gray-400 mt-1 break-all">{{ $website->url }}</p>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            @include('websites.partials.module-tabs', ['active' => 'campaign', 'website' => $website])
        </div>

        @if (session('status'))
            <div class="rounded-lg bg-emerald-900/40 border border-emerald-700 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-slate-800/60 rounded-xl p-6 shadow">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-medium text-white">Analysis</h3>
                    <p class="text-sm text-gray-400 mt-1">
                        Runs a bounded crawl (max 20 pages) via the crawler runner, stores extracted page data, and feeds the intelligence modules.
                    </p>
                </div>
                <form method="POST" action="{{ route('websites.analyze', $website) }}">
                    @csrf
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition">
                        Start Analysis
                    </button>
                </form>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                Last analyzed: {{ $website->last_analyzed_at ? $website->last_analyzed_at->format('Y-m-d H:i') : 'never' }}
            </p>
        </div>

        <div class="bg-slate-800/60 rounded-xl shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700">
                <h3 class="text-lg font-medium text-white">Analysis history</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase text-gray-400 bg-slate-900/60">
                        <tr>
                            <th class="px-6 py-3 text-left">Job</th>
                            <th class="px-6 py-3 text-left">Status</th>
                            <th class="px-6 py-3 text-left">Pages</th>
                            <th class="px-6 py-3 text-left">Attempt</th>
                            <th class="px-6 py-3 text-left">Created</th>
                            <th class="px-6 py-3 text-left">Finished</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/60">
                        @forelse ($jobs as $job)
                            <tr>
                                <td class="px-6 py-3 font-mono text-xs text-gray-300">{{ $job->uuid }}</td>
                                <td class="px-6 py-3"><x-status-badge :status="$job->status" /></td>
                                <td class="px-6 py-3 text-gray-300">
                                    {{ $job->status === 'completed' ? $job->crawled_pages : '—' }}
                                </td>
                                <td class="px-6 py-3 text-gray-300">{{ $job->attempt }}</td>
                                <td class="px-6 py-3 text-gray-400">{{ $job->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-3 text-gray-400">{{ $job->finished_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                            @if ($job->status === 'failed')
                                <tr>
                                    <td colspan="6" class="px-6 py-2 text-xs text-rose-300/90">
                                        {{ $job->failure_reason ? 'Reason: '.$job->failure_reason.' — ' : '' }}{{ $job->error_message }}
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    No analyses yet. Start the first analysis above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @php
            $completedJob = $jobs->firstWhere('status', 'completed');
            $completedPages = $completedJob
                ? $completedJob->crawledPages()->orderBy('depth')->orderBy('id')->take(20)->get()
                : collect();
            $completedErrors = $completedJob
                ? $completedJob->crawlErrors()->orderBy('id')->take(20)->get()
                : collect();
            $criteria = collect($readinessScore?->breakdown['criteria'] ?? []);
        @endphp

        @if ($completedJob)
            <div class="bg-slate-800/60 rounded-xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-700 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-lg font-medium text-white">Latest crawl results</h3>
                    <span class="text-xs text-gray-400 font-mono">{{ $completedJob->uuid }}</span>
                </div>
                <div class="px-6 py-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs uppercase text-gray-400">Pages crawled</p>
                        <p class="text-2xl font-semibold text-white">{{ $completedJob->crawled_pages }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400">Crawl duration</p>
                        <p class="text-2xl font-semibold text-white">
                            {{ $completedJob->crawl_duration_ms ? number_format($completedJob->crawl_duration_ms / 1000, 1).' s' : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400">Crawl errors</p>
                        <p class="text-2xl font-semibold text-white">{{ $completedErrors->count() }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400">Finished</p>
                        <p class="text-2xl font-semibold text-white">{{ $completedJob->finished_at?->format('H:i') }}</p>
                    </div>
                </div>
                <div class="overflow-x-auto border-t border-slate-700/60">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase text-gray-400 bg-slate-900/60">
                            <tr>
                                <th class="px-6 py-3 text-left">Page</th>
                                <th class="px-6 py-3 text-left">HTTP</th>
                                <th class="px-6 py-3 text-left">Depth</th>
                                <th class="px-6 py-3 text-left">Text length</th>
                                <th class="px-6 py-3 text-left">Load</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/60">
                            @forelse ($completedPages as $page)
                                <tr>
                                    <td class="px-6 py-3">
                                        <p class="text-gray-200">{{ $page->title ?: '(untitled)' }}</p>
                                        <p class="text-xs text-gray-500 font-mono break-all">{{ $page->final_url ?: $page->url }}</p>
                                    </td>
                                    <td class="px-6 py-3 text-gray-300">{{ $page->http_status }}</td>
                                    <td class="px-6 py-3 text-gray-300">{{ $page->depth }}</td>
                                    <td class="px-6 py-3 text-gray-300">{{ number_format($page->text_length) }}</td>
                                    <td class="px-6 py-3 text-gray-300">
                                        {{ $page->load_time_ms ? $page->load_time_ms.' ms' : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-6 text-center text-gray-500">No pages stored for this job.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($completedErrors->isNotEmpty())
                    <div class="px-6 py-4 border-t border-slate-700/60">
                        <h4 class="text-sm font-medium text-rose-300 mb-2">Crawl errors</h4>
                        <ul class="space-y-1 text-xs text-rose-200/80">
                            @foreach ($completedErrors as $error)
                                <li>
                                    <span class="font-mono">{{ $error->error_type }}</span>
                                    @if ($error->url)
                                        <span class="text-gray-400">{{ $error->url }}</span> —
                                    @endif
                                    {{ $error->message }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-medium text-white">Campaign Intelligence</h3>
                    @if ($readinessScore)
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-xs uppercase text-gray-400">Heuristic Campaign Readiness Score</p>
                                <p class="text-3xl font-semibold text-emerald-400">{{ $readinessScore->score ?? 'n/a' }}<span class="text-base text-gray-500">/100</span></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs uppercase text-gray-400">Confidence</p>
                                <p class="text-sm font-medium text-gray-300">{{ $readinessScore->breakdown['confidence'] ?? '—' }}</p>
                            </div>
                        </div>
                    @endif
                </div>
                @if ($readinessScore)
                    <p class="text-xs text-gray-500 mt-2">
                        {{ $readinessScore->breakdown['summary'] ?? '' }} Weighted heuristic methodology, URL-only evidence —
                        not a validated prediction of campaign performance. Version {{ $readinessScore->version }}.
                    </p>
                    <div class="overflow-x-auto mt-4 border-t border-slate-700/60">
                        <table class="min-w-full text-sm">
                            <thead class="text-xs uppercase text-gray-400 bg-slate-900/60">
                                <tr>
                                    <th class="px-4 py-3 text-left">Criterion</th>
                                    <th class="px-4 py-3 text-left">Score</th>
                                    <th class="px-4 py-3 text-left">Weight</th>
                                    <th class="px-4 py-3 text-left">Evidence</th>
                                    <th class="px-4 py-3 text-left">Recommendation</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/60">
                                @foreach ($criteria as $criterion)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-200">
                                            {{ $criterion['label'] }}
                                            <p class="text-xs text-gray-500">{{ $criterion['explanation'] }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($criterion['score'] === null)
                                                <span class="text-xs text-gray-500">unknown</span>
                                            @else
                                                <span class="text-gray-200">{{ $criterion['score'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-400">{{ $criterion['weight'] }}%</td>
                                        <td class="px-4 py-3 text-xs text-gray-400">
                                            <ul class="space-y-0.5">
                                                @forelse ($criterion['evidence'] as $item)
                                                    <li class="break-words">{{ $item }}</li>
                                                @empty
                                                    <li class="text-gray-500">No evidence recorded.</li>
                                                @endforelse
                                            </ul>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-300">{{ $criterion['recommendation'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-400 mt-3">
                        Campaign readiness scoring has not been computed for the latest crawl data yet. Reprocess with
                        <code class="text-emerald-300">php artisan analysis:reprocess {{ $completedJob?->uuid }}</code> to generate it.
                    </p>
                @endif
            </div>

            <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
                <h3 class="text-lg font-medium text-white">Technical audit</h3>
                @if ($technicalAudit)
                    <div class="flex items-center gap-3 mt-2">
                        <p class="text-2xl font-semibold text-white">{{ $technicalAudit->score ?? 'n/a' }}<span class="text-sm text-gray-500">/100</span></p>
                        <span class="text-xs text-gray-500">heuristic check score · version {{ $technicalAudit->version }}</span>
                    </div>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($technicalAudit->checks ?? [] as $check)
                            <li class="flex flex-wrap items-start gap-2">
                                @php
                                    $statusColors = [
                                        'pass' => 'text-emerald-400 bg-emerald-900/30 border-emerald-700/50',
                                        'warn' => 'text-amber-300 bg-amber-900/30 border-amber-700/50',
                                        'fail' => 'text-rose-300 bg-rose-900/30 border-rose-700/50',
                                        'unknown' => 'text-gray-400 bg-slate-900/60 border-slate-600/50',
                                    ];
                                @endphp
                                <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $statusColors[$check['status']] ?? $statusColors['unknown'] }}">
                                    {{ $check['status'] }}
                                </span>
                                <span class="text-gray-200">{{ $check['label'] }}</span>
                                @if ($check['value'])
                                    <span class="text-xs text-gray-400">{{ $check['value'] }}</span>
                                @endif
                                @if (! empty($check['evidence']))
                                    <ul class="w-full text-xs text-gray-500">
                                        @foreach ($check['evidence'] as $item)
                                            <li class="break-all">{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-400 mt-2">No technical audit stored for the latest completed job yet.</p>
                @endif
            </div>

            <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
                <h3 class="text-lg font-medium text-white">Findings &amp; recommendations</h3>
                @if ($insights->isNotEmpty())
                    <ul class="mt-4 space-y-3">
                        @foreach ($insights as $insight)
                            <li class="rounded-lg border border-slate-700/60 bg-slate-900/40 p-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    @php
                                        $priorityColors = [
                                            'high' => 'text-rose-300 bg-rose-900/30 border-rose-700/50',
                                            'medium' => 'text-amber-300 bg-amber-900/30 border-amber-700/50',
                                            'low' => 'text-sky-300 bg-sky-900/30 border-sky-700/50',
                                        ];
                                    @endphp
                                    <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $priorityColors[$insight->priority] ?? '' }}">{{ $insight->priority }}</span>
                                    <span class="text-sm text-gray-200">{{ $insight->title }}</span>
                                    <span class="text-xs text-gray-500">effort: {{ $insight->effort }} · confidence: {{ $insight->confidence }}</span>
                                </div>
                                @if ($insight->evidence)
                                    <ul class="mt-2 text-xs text-gray-500 space-y-0.5">
                                        @foreach ($insight->evidence as $item)
                                            <li class="break-words">Evidence: {{ $item }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if ($insight->recommendation)
                                    <p class="mt-2 text-sm text-gray-300">{{ $insight->recommendation }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-400 mt-2">No findings recorded — every scored criterion reached its threshold for this job.</p>
                @endif
                <p class="text-xs text-gray-500 mt-4">
                    The AI Marketing Advisor (evidence-based audit and prioritised action plan) is produced by Phase 5.
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
