<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">Wasted Spend &mdash; {{ $website->label ?: $website->host }}</h2>
        <p class="text-sm text-slate-500 mt-1">Conversion-leakage indicators from crawl evidence. Actual wasted advertising spend requires ad-cost and conversion integrations.</p>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            @include('websites.partials.module-tabs', ['active' => 'wasted', 'website' => $website])
        </div>

        @if ($job === null)
            @include('websites.partials.module-empty', ['website' => $website])
        @else
            @php
                $severityColors = [
                    'high' => 'text-rose-700 bg-rose-50 border-rose-200',
                    'medium' => 'text-amber-700 bg-amber-50 border-amber-200',
                    'low' => 'text-indigo-600 bg-indigo-50 border-indigo-200',
                ];
            @endphp

            <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow border border-slate-200/70">
                <ul class="space-y-4">
                    @forelse ($findings as $finding)
                        <li class="rounded-lg border border-slate-200/70 bg-slate-50 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $severityColors[$finding->severity] ?? '' }}">{{ $finding->severity }}</span>
                                <span class="text-sm text-slate-900 font-medium">{{ $finding->title }}</span>
                            </div>
                            @if ($finding->evidence)
                                <ul class="mt-2 text-xs text-slate-500 space-y-0.5">
                                    @foreach ($finding->evidence as $item)
                                        <li class="break-words">Evidence: {{ $item }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($finding->potential_impact)
                                <p class="mt-2 text-sm text-slate-600"><span class="text-slate-500 text-xs uppercase">Potential marketing impact:</span> {{ $finding->potential_impact }}</p>
                            @endif
                            @if ($finding->correction)
                                <p class="mt-1 text-sm text-emerald-700"><span class="text-slate-500 text-xs uppercase">Recommended correction:</span> {{ $finding->correction }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">No findings stored for this job yet.</li>
                    @endforelse
                </ul>
                <p class="text-xs text-slate-500 mt-4">
                    These findings are website conversion-leakage indicators only. Actual wasted advertising spend cannot be identified without advertising cost and conversion data (integrations, future phase).
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
