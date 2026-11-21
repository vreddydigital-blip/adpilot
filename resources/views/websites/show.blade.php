<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $website->label ?: $website->host }}
        </h2>
        <p class="text-sm text-gray-400 mt-1 break-all">{{ $website->url }}</p>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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
                <h3 class="text-lg font-medium text-white">Intelligence modules</h3>
                <p class="text-sm text-gray-400 mt-2">
                    Crawl data is stored. Module scoring (campaign readiness, conversion opportunities, budget tools, lead readiness,
                    creative analysis, advisor) is produced by Phases 3–5 and will appear here once implemented. No scores are
                    calculated yet, so none are shown.
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
