<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">Ad Creative Intelligence &mdash; {{ $website->label ?: $website->host }}</h2>
        <p class="text-sm text-gray-400 mt-1">Ad copy drafts assembled from your own page titles, headings, descriptions and CTA labels. No CTR or conversion predictions.</p>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            @include('websites.partials.module-tabs', ['active' => 'creative', 'website' => $website])
        </div>

        @if ($job === null)
            @include('websites.partials.module-empty', ['website' => $website])
        @else
            @php
                $matchVerdicts = [
                    'good' => 'text-emerald-300 bg-emerald-900/30 border-emerald-700/50',
                    'partial' => 'text-amber-300 bg-amber-900/30 border-amber-700/50',
                    'low' => 'text-rose-300 bg-rose-900/30 border-rose-700/50',
                    'unknown' => 'text-gray-400 bg-slate-900/60 border-slate-600/50',
                ];
            @endphp

            @if ($messageMatch)
                <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-medium text-white">Message match (ad words vs landing-page words)</h3>
                        <div class="flex items-center gap-3">
                            <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $matchVerdicts[$messageMatch['verdict']] ?? '' }}">{{ $messageMatch['verdict'] }}</span>
                            <p class="text-2xl font-semibold text-white">{{ $messageMatch['overlap_percent'] ?? 'n/a' }}<span class="text-base text-gray-500">%</span></p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">{{ $messageMatch['note'] ?? '' }}</p>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
                    <h3 class="text-lg font-medium text-white">Google Search headlines <span class="text-xs text-gray-500">(max 30 chars)</span></h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        @forelse ($googleHeadlines as $headline)
                            <li class="flex items-center justify-between gap-3 rounded border border-slate-700/60 bg-slate-900/40 px-3 py-2">
                                <span class="text-gray-200">{{ $headline->headline }}</span>
                                <span class="text-xs text-gray-500">{{ $headline->char_length }}/30</span>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">No headlines could be generated from the captured page text.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
                    <h3 class="text-lg font-medium text-white">Google Search descriptions <span class="text-xs text-gray-500">(max 90 chars)</span></h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        @forelse ($googleDescriptions as $description)
                            <li class="rounded border border-slate-700/60 bg-slate-900/40 px-3 py-2">
                                <p class="text-gray-200">{{ $description->text }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $description->char_length }}/90</p>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">No descriptions could be generated from the captured page text.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
                    <h3 class="text-lg font-medium text-white">Meta headlines <span class="text-xs text-gray-500">(max 40 chars)</span></h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        @forelse ($metaHeadlines as $headline)
                            <li class="flex items-center justify-between gap-3 rounded border border-slate-700/60 bg-slate-900/40 px-3 py-2">
                                <span class="text-gray-200">{{ $headline->headline }}</span>
                                <span class="text-xs text-gray-500">{{ $headline->char_length }}/40</span>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">No Meta headlines generated.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
                    <h3 class="text-lg font-medium text-white">Meta primary text <span class="text-xs text-gray-500">(max 125 chars)</span></h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        @forelse ($metaPrimary as $text)
                            <li class="rounded border border-slate-700/60 bg-slate-900/40 px-3 py-2">
                                <p class="text-gray-200">{{ $text->text }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $text->char_length }}/125</p>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">No Meta primary text generated.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
                <h3 class="text-lg font-medium text-white">Campaign messaging angles</h3>
                <ul class="mt-3 space-y-1 text-sm text-gray-300 list-disc list-inside">
                    @forelse ($angles as $angle)
                        <li>{{ $angle->text }}</li>
                    @empty
                        <li class="text-gray-500 list-none">No messaging angles stored for this job.</li>
                    @endforelse
                </ul>
                <p class="text-xs text-gray-500 mt-4">
                    Deterministic templates assembled from your website&rsquo;s own titles, headings, descriptions and CTA labels. No CTR or conversion predictions are made.
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
