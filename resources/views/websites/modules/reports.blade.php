<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">Reports &mdash; {{ $website->label ?: $website->host }}</h2>
        <p class="text-sm text-slate-500 mt-1">Downloadable PDF/CSV reports of the stored analysis, plus the score history across the latest completed jobs.</p>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            @include('websites.partials.module-tabs', ['active' => 'reports', 'website' => $website])
        </div>

        @if (session('success'))
            <div class="max-w-5xl mx-auto px-4 sm:px-6">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
            </div>
        @endif
        @if (session('error'))
            <div class="max-w-5xl mx-auto px-4 sm:px-6">
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
            </div>
        @endif

        @if ($job === null)
            @include('websites.partials.module-empty', ['website' => $website, 'message' => 'Reports require a completed crawl. Start an analysis on the website page first.'])
        @else
            <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-6">
                <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow border border-slate-200/70">
                    <h3 class="text-base font-semibold text-slate-900">Generate a report</h3>
                    <p class="text-sm text-slate-500 mt-1">Snapshots the stored results of the latest completed analysis ({{ $job->uuid }}) into a PDF or CSV document, including disclaimers.</p>
                    <div class="flex flex-wrap gap-3 mt-4">
                        <form method="POST" action="{{ route('websites.reports.store', $website) }}">
                            @csrf
                            <input type="hidden" name="format" value="pdf">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">Generate PDF report</button>
                        </form>
                        <form method="POST" action="{{ route('websites.reports.store', $website) }}">
                            @csrf
                            <input type="hidden" name="format" value="csv">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">Generate CSV report</button>
                        </form>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow border border-slate-200/70">
                    <h3 class="text-base font-semibold text-slate-900">Score history (Chart.js, last {{ count($history) }} completed job(s))</h3>
                    <p class="text-xs text-slate-500 mt-1">Module scores across completed analysis jobs, oldest to newest. Not a marketing performance chart - all values are heuristic crawl-evidence scores.</p>
                    <div class="mt-4 bg-slate-50 rounded-lg p-4">
                        <canvas id="history-chart" data-history='@json($history)' aria-label="Module score history chart" role="img"></canvas>
                        @if (count($history) < 2)
                            <p class="text-xs text-slate-500 mt-2">Run more analyses to see trend lines.</p>
                        @endif
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow border border-slate-200/70">
                    <h3 class="text-base font-semibold text-slate-900">Stored reports</h3>
                    @if ($reports->isEmpty())
                        <p class="text-sm text-slate-500 mt-2">No reports generated yet. Use the buttons above.</p>
                    @else
                        <table class="mt-3 w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase text-slate-500 border-b border-slate-200">
                                    <th class="py-2">Title</th>
                                    <th class="py-2">Format</th>
                                    <th class="py-2">Size</th>
                                    <th class="py-2">Generated</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-600">
                                @foreach ($reports as $report)
                                    <tr class="border-b border-slate-200">
                                        <td class="py-2 pr-2">{{ $report->title }}</td>
                                        <td class="py-2 pr-2 uppercase">{{ $report->format }}</td>
                                        <td class="py-2 pr-2">{{ number_format(($report->size_bytes ?? 0) / 1024, 1) }} KB</td>
                                        <td class="py-2 pr-2">{{ $report->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="py-2 text-right">
                                            <a href="{{ route('websites.reports.download', [$website, $report]) }}" class="text-indigo-600 hover:text-indigo-600 underline">Download</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    <p class="text-xs text-slate-500 mt-4">Report files live on the local disk; on hosts with an ephemeral filesystem (Render free tier) they disappear on redeploy - the Reports screen regenerates them deterministically from the stored analysis when downloading.</p>
                </div>
            </div>

            <script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
            <script>
                (function () {
                    var canvas = document.getElementById('history-chart');
                    if (!canvas || typeof window.Chart !== 'function') { return; }
                    var history = JSON.parse(canvas.dataset.history || '[]');
                    if (!history.length) { return; }
                    var moduleColors = {
                        campaign_readiness: '#38bdf8',
                        technical_audit: '#34d399',
                        conversion_opportunity: '#a78bfa',
                        lead_intelligence: '#fbbf24',
                        marketing_advisor: '#fb7185'
                    };
                    var moduleLabels = {
                        campaign_readiness: 'Campaign readiness',
                        technical_audit: 'Technical audit',
                        conversion_opportunity: 'Conversion opportunity',
                        lead_intelligence: 'Lead readiness',
                        marketing_advisor: 'Priority score'
                    };
                    var labels = history.map(function (item) { return (item.finished_at || '').slice(0, 10); });
                    var datasets = Object.keys(moduleColors).map(function (module) {
                        return {
                            label: moduleLabels[module],
                            data: history.map(function (item) { return item.scores[module] === null ? null : item.scores[module]; }),
                            borderColor: moduleColors[module],
                            backgroundColor: moduleColors[module],
                            spanGaps: true,
                            tension: 0.25,
                            pointRadius: 3
                        };
                    });
                    new Chart(canvas, {
                        type: 'line',
                        data: { labels: labels, datasets: datasets },
                        options: {
                            responsive: true,
                            scales: {
                                y: { beginAtZero: true, max: 100, ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } },
                                x: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } }
                            },
                            plugins: { legend: { labels: { color: '#e2e8f0' } } }
                        }
                    });
                })();
            </script>
        @endif
    </div>
</x-app-layout>
