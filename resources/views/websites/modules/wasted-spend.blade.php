<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">Wasted Spend &mdash; {{ $website->label ?: $website->host }}</h2>
        <p class="text-sm text-gray-400 mt-1">Conversion-leakage indicators from crawl evidence. Actual wasted advertising spend requires ad-cost and conversion integrations.</p>
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
                    'high' => 'text-rose-300 bg-rose-900/30 border-rose-700/50',
                    'medium' => 'text-amber-300 bg-amber-900/30 border-amber-700/50',
                    'low' => 'text-sky-300 bg-sky-900/30 border-sky-700/50',
                ];
            @endphp

            <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
                <ul class="space-y-4">
                    @forelse ($findings as $finding)
                        <li class="rounded-lg border border-slate-700/60 bg-slate-900/40 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $severityColors[$finding->severity] ?? '' }}">{{ $finding->severity }}</span>
                                <span class="text-sm text-gray-200 font-medium">{{ $finding->title }}</span>
                            </div>
                            @if ($finding->evidence)
                                <ul class="mt-2 text-xs text-gray-500 space-y-0.5">
                                    @foreach ($finding->evidence as $item)
                                        <li class="break-words">Evidence: {{ $item }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($finding->potential_impact)
                                <p class="mt-2 text-sm text-gray-300"><span class="text-gray-500 text-xs uppercase">Potential marketing impact:</span> {{ $finding->potential_impact }}</p>
                            @endif
                            @if ($finding->correction)
                                <p class="mt-1 text-sm text-emerald-200/90"><span class="text-gray-500 text-xs uppercase">Recommended correction:</span> {{ $finding->correction }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">No findings stored for this job yet.</li>
                    @endforelse
                </ul>
                <p class="text-xs text-gray-500 mt-4">
                    These findings are website conversion-leakage indicators only. Actual wasted advertising spend cannot be identified without advertising cost and conversion data (integrations, future phase).
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
