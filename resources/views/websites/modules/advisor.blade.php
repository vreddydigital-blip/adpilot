<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">AI Marketing Advisor &mdash; {{ $website->label ?: $website->host }}</h2>
        <p class="text-sm text-slate-500 mt-1">Consolidated, prioritised action plan built from the six analysis modules' crawl evidence.</p>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            @include('websites.partials.module-tabs', ['active' => 'advisor', 'website' => $website])
        </div>

        @if ($job === null)
            @include('websites.partials.module-empty', ['website' => $website])
        @else
            @php
                $breakdown = $score?->breakdown ?? [];
                $moduleScores = $breakdown['scores'] ?? [];
                $priorities = $breakdown['priorities'] ?? ['high' => 0, 'medium' => 0, 'low' => 0];
                $priorityColors = [
                    'high' => 'text-rose-700 bg-rose-50 border-rose-200',
                    'medium' => 'text-amber-700 bg-amber-50 border-amber-200',
                    'low' => 'text-indigo-600 bg-indigo-50 border-indigo-200',
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow border border-slate-200/70 text-center">
                    <p class="text-xs uppercase text-slate-500">Priority score</p>
                    <p class="text-3xl font-bold text-emerald-600 mt-1">{{ $score?->score ?? 'n/a' }}</p>
                    <p class="text-[11px] text-slate-500 mt-1">100 &minus; mean of known module scores</p>
                </div>
                @foreach (['campaign_readiness' => 'Campaign readiness', 'technical_audit' => 'Technical audit', 'conversion_opportunity' => 'Conversion opportunity', 'lead_intelligence' => 'Lead readiness'] as $key => $label)
                    <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow border border-slate-200/70 text-center">
                        <p class="text-xs uppercase text-slate-500">{{ $label }}</p>
                        <p class="text-2xl font-semibold text-slate-900 mt-1">{{ $moduleScores[$key] ?? 'n/a' }}{{ isset($moduleScores[$key]) ? '/100' : '' }}</p>
                    </div>
                @endforeach
            </div>

            <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow border border-slate-200/70">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-semibold text-slate-900">Executive summary</h3>
                    <span class="px-2 py-0.5 rounded border text-xs uppercase text-emerald-700 bg-emerald-50 border-emerald-200">engine: {{ $breakdown['engine'] ?? 'rule_based_v1' }}</span>
                    <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $priorityColors['high'] }}">high: {{ $priorities['high'] ?? 0 }}</span>
                    <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $priorityColors['medium'] }}">medium: {{ $priorities['medium'] ?? 0 }}</span>
                    <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $priorityColors['low'] }}">low: {{ $priorities['low'] ?? 0 }}</span>
                </div>
                <p class="text-sm text-slate-600 mt-3">{{ $breakdown['summary'] ?? 'No advisor summary stored for this job yet.' }}</p>
                <p class="text-xs text-slate-500 mt-3">{{ $breakdown['engine_note'] ?? 'Deterministic rule engine output; not a large language model and not a validated ML prediction.' }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $breakdown['disclaimer'] ?? '' }}</p>
            </div>

            <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow border border-slate-200/70">
                <h3 class="text-base font-semibold text-slate-900 mb-4">Prioritised action plan</h3>
                <ol class="space-y-4 list-none">
                    @forelse ($recommendations as $recommendation)
                        <li class="rounded-lg border border-slate-200/70 bg-slate-50 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-slate-500 text-sm font-mono">#{{ $recommendation->position }}</span>
                                <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $priorityColors[$recommendation->priority] ?? '' }}">{{ $recommendation->priority }}</span>
                                <span class="text-sm text-slate-900 font-medium">{{ $recommendation->title }}</span>
                            </div>
                            @if ($recommendation->rationale)
                                <p class="mt-2 text-xs text-slate-500">{{ $recommendation->rationale }}</p>
                            @endif
                            @if ($recommendation->evidence)
                                <ul class="mt-2 text-xs text-slate-500 space-y-0.5">
                                    @foreach ($recommendation->evidence as $item)
                                        <li class="break-words">Evidence: {{ $item }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($recommendation->action)
                                <p class="mt-2 text-sm text-emerald-700"><span class="text-slate-500 text-xs uppercase">Action:</span> {{ $recommendation->action }}</p>
                            @endif
                            <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-slate-500">
                                <span>modules: {{ implode(', ', $recommendation->module_source ?? []) }}</span>
                                @if ($recommendation->effort)<span>effort: {{ $recommendation->effort }}</span>@endif
                                @if ($recommendation->expected_impact)<span>expected impact: {{ $recommendation->expected_impact }}</span>@endif
                                @if ($recommendation->confidence)<span>confidence: {{ $recommendation->confidence }}</span>@endif
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">No recommendations stored for this job yet. Re-run the analysis to generate the advisor output.</li>
                    @endforelse
                </ol>
            </div>
        @endif
    </div>
</x-app-layout>
