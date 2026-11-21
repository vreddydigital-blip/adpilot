<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                <a href="{{ route('projects.show', $website->project) }}" class="hover:underline">{{ $website->project->name }}</a>
            </p>
            <h2 class="mt-1 font-semibold text-xl text-slate-900 leading-tight">{{ $website->host }}</h2>
            <p class="mt-1 text-sm text-slate-500 break-all">{{ $website->normalized_url }}</p>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-6">
        <section class="bg-white border border-slate-200 shadow-sm rounded-lg p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Website analysis</h3>
                    <p class="mt-1 text-sm text-slate-500">Runs a bounded crawl of this website and stores extracted evidence for the intelligence modules.</p>
                    <p class="mt-1 text-xs text-amber-700">The crawler runner is delivered in Phase 2. Jobs created now stay in &ldquo;pending&rdquo; until then &mdash; they will not be marked completed.</p>
                </div>
                <form method="POST" action="{{ route('websites.analyze', $website) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-md bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800">Start Analysis</button>
                </form>
            </div>
        </section>

        <section class="bg-white border border-slate-200 shadow-sm rounded-lg">
            <h3 class="text-sm font-semibold text-slate-900 px-6 pt-5">Analysis history</h3>
            @if ($jobs->isEmpty())
                <div class="p-6 text-sm text-slate-400">No analyses yet for this website. Use &ldquo;Start Analysis&rdquo; above to create one.</div>
            @else
                <div class="px-6 py-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-200">
                                <th class="py-2 pe-4">Job</th>
                                <th class="py-2 pe-4">Status</th>
                                <th class="py-2 pe-4">Attempt</th>
                                <th class="py-2 pe-4">Created</th>
                                <th class="py-2">Finished</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($jobs as $job)
                                <tr>
                                    <td class="py-3 pe-4 font-mono text-xs text-slate-600">{{ Str::limit($job->uuid, 8, '') }}&hellip;</td>
                                    <td class="py-3 pe-4"><x-status-badge :status="$job->status" /></td>
                                    <td class="py-3 pe-4 text-slate-600">{{ $job->attempt }}</td>
                                    <td class="py-3 pe-4 text-slate-600">{{ $job->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="py-3 text-slate-600">{{ $job->finished_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                </tr>
                                @if ($job->error_message)
                                    <tr class="bg-rose-50/50">
                                        <td class="py-2 text-xs text-rose-700" colspan="5">Error: {{ $job->error_message }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="bg-white border border-slate-200 shadow-sm rounded-lg p-6">
            <h3 class="text-sm font-semibold text-slate-900 mb-2">Module results</h3>
            <p class="text-sm text-slate-400">
                Campaign readiness, conversion opportunities, budget scenarios, wasted-spend findings, lead capture audit,
                ad creative intelligence and advisor recommendations will appear here after the crawler and intelligence
                modules have processed a completed analysis (Phase 2+). Nothing is displayed until real results exist.
            </p>
        </section>
    </div>
</x-app-layout>
