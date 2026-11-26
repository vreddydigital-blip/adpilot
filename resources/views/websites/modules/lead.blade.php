<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">Lead Intelligence &mdash; {{ $website->label ?: $website->host }}</h2>
        <p class="text-sm text-slate-500 mt-1">Lead capture readiness from crawl evidence. Actual leads and lead quality require CRM integrations (future phase).</p>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            @include('websites.partials.module-tabs', ['active' => 'lead', 'website' => $website])
        </div>

        @if ($job === null)
            @include('websites.partials.module-empty', ['website' => $website])
        @else
            @php
                $forms = collect($score?->breakdown['forms'] ?? []);
                $contacts = $score?->breakdown['contact_methods'] ?? [];
                $contactLabels = ['email' => 'Email link (mailto:)', 'phone' => 'Phone link (tel:)', 'whatsapp' => 'WhatsApp click-to-chat', 'booking' => 'Booking / calendar link'];
            @endphp

            <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow border border-slate-200/70">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h3 class="text-lg font-medium text-slate-900">Heuristic Lead Capture Readiness Score</h3>
                    <p class="text-3xl font-semibold text-emerald-600">{{ $score?->score ?? 'n/a' }}<span class="text-base text-slate-500">/100</span></p>
                </div>
                <p class="text-xs text-slate-500 mt-2">{{ $score?->breakdown['summary'] ?? '' }} Presence-based heuristic, URL-only evidence &mdash; not real lead quality. Version {{ $score?->version ?? 'n/a' }}.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-5">
                    <div>
                        <h4 class="text-sm font-medium text-slate-600 mb-2">Forms detected</h4>
                        @if ($forms->isNotEmpty())
                            <ul class="space-y-1 text-sm text-slate-600">
                                @foreach ($forms as $form)
                                    <li class="font-mono text-xs">
                                        {{ $form['field_count'] ?? '?' }}-field
                                        {{ strtoupper($form['method'] ?? 'form') }} form
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-slate-500">No submit-able forms detected in the crawl.</p>
                        @endif
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-slate-600 mb-2">Contact channels</h4>
                        <ul class="space-y-1 text-sm">
                            @foreach ($contactLabels as $key => $label)
                                <li class="flex items-center gap-2">
                                    <span class="{{ ($contacts[$key] ?? false) ? 'text-emerald-600' : 'text-slate-600' }}">{{ ($contacts[$key] ?? false) ? '●' : '○' }}</span>
                                    <span class="{{ ($contacts[$key] ?? false) ? 'text-slate-900' : 'text-slate-500' }}">{{ $label }}</span>
                                    @unless ($contacts[$key] ?? false)
                                        <span class="text-xs text-amber-700">(missing)</span>
                                    @endunless
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow border border-slate-200/70">
                <h3 class="text-lg font-medium text-slate-900">Lead capture opportunities</h3>
                @php
                    $priorityColors = [
                        'high' => 'text-rose-700 bg-rose-50 border-rose-200',
                        'medium' => 'text-amber-700 bg-amber-50 border-amber-200',
                        'low' => 'text-indigo-600 bg-indigo-50 border-indigo-200',
                    ];
                @endphp
                <ul class="mt-4 space-y-3">
                    @forelse ($insights as $insight)
                        <li class="rounded-lg border border-slate-200/70 bg-slate-50 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2 py-0.5 rounded border text-xs uppercase {{ $priorityColors[$insight->priority] ?? '' }}">{{ $insight->priority }}</span>
                                <span class="text-sm text-slate-900 font-medium">{{ $insight->title }}</span>
                                <span class="text-xs text-slate-500">effort: {{ $insight->effort }}</span>
                            </div>
                            @if ($insight->evidence)
                                <ul class="mt-2 text-xs text-slate-500 space-y-0.5">
                                    @foreach ($insight->evidence as $item)
                                        <li class="break-words">Evidence: {{ $item }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <p class="mt-2 text-sm text-slate-600">{{ $insight->recommendation }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">No lead-capture insights stored for this job yet.</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
