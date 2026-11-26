<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">Predictive Analytics &mdash; {{ $website->label ?: $website->host }}</h2>
        <p class="text-sm text-slate-500 mt-1">Heuristic Conversion Opportunity Score derived from crawl evidence. Not a validated conversion probability.</p>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            @include('websites.partials.module-tabs', ['active' => 'predictive', 'website' => $website])
        </div>

        @if ($job === null)
            @include('websites.partials.module-empty', ['website' => $website])
        @else
            @php
                $priorityCounts = $opportunities->countBy('priority');
                $scoreColors = [
                    'high' => 'text-rose-700 bg-rose-50 border-rose-200',
                    'medium' => 'text-amber-700 bg-amber-50 border-amber-200',
                    'low' => 'text-indigo-600 bg-indigo-50 border-indigo-200',
                ];
            @endphp

            <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow border border-slate-200/70">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-medium text-slate-900">Heuristic Conversion Opportunity Score</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Methodology: severity &times; impact &times; confidence per opportunity, normalized to 0&ndash;100 over known opportunities only. Version {{ $score?->version ?? 'n/a' }}.
                        </p>
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="text-center">
                            <p class="text-xs uppercase text-slate-500">High</p>
                            <p class="text-2xl font-semibold text-rose-700">{{ $priorityCounts->get('high', 0) }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs uppercase text-slate-500">Medium</p>
                            <p class="text-2xl font-semibold text-amber-700">{{ $priorityCounts->get('medium', 0) }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs uppercase text-slate-500">Low</p>
                            <p class="text-2xl font-semibold text-indigo-600">{{ $priorityCounts->get('low', 0) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs uppercase text-slate-500">Overall score</p>
                            <p class="text-3xl font-semibold text-slate-900">{{ $score?->score ?? 'n/a' }}<span class="text-base text-slate-500">/100</span></p>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-3">Higher score = more identified conversion opportunities to act on. Job {{ $job->uuid }}.</p>
            </div>

            <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow border border-slate-200/70 space-y-4">
                @forelse ($opportunities as $opportunity)
                    <div class="rounded-lg border border-slate-200/70 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $scoreColors[$opportunity->priority] ?? '' }}">{{ $opportunity->priority }}</span>
                            <span class="text-sm text-slate-900 font-medium">{{ $opportunity->title }}</span>
                            <span class="text-xs text-slate-500">
                                opportunity score {{ $opportunity->score ?? 'n/a' }} &middot; severity {{ $opportunity->severity ?? 'n/a' }} &middot;
                                impact {{ $opportunity->impact ?? 'n/a' }} &middot; confidence {{ $opportunity->confidence ?? 'n/a' }}% &middot;
                                effort {{ $opportunity->effort ?? 'n/a' }} &middot; {{ str_replace('_', ' ', $opportunity->direction) }}
                            </span>
                        </div>
                        @if ($opportunity->evidence)
                            <ul class="mt-2 text-xs text-slate-500 space-y-0.5">
                                @foreach ($opportunity->evidence as $item)
                                    <li class="break-words">Evidence: {{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <p class="mt-2 text-sm text-slate-600">{{ $opportunity->recommendation }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No conversion opportunities stored for this job. Reprocess it to generate them.</p>
                @endforelse
            </div>
        @endif
    </div>
</x-app-layout>
