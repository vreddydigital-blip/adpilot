<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">Overview</h2>
        <p class="mt-1 text-sm text-slate-500">Real counts from your own data. Nothing here is simulated.</p>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-6">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
            <x-stat-card label="Projects" :value="$stats['projects']" />
            <x-stat-card label="Websites" :value="$stats['websites']" />
            <x-stat-card label="Completed analyses" :value="$stats['completed']" />
            <x-stat-card label="Pending analyses" :value="$stats['pending']" />
            <x-stat-card label="Failed analyses" :value="$stats['failed']" />
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <section class="bg-white border border-slate-200 shadow-sm rounded-lg p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-3">Latest analysis</h3>
                @if ($latestJob)
                    <p class="text-sm text-slate-700">
                        {{ $latestJob->website->host }}
                        <span class="mx-2">&mdash;</span>
                        <x-status-badge :status="$latestJob->status" />
                    </p>
                    <p class="mt-2 text-xs text-slate-400">Job #{{ $latestJob->id }} &middot; created {{ $latestJob->created_at->diffForHumans() }}</p>
                    <a href="{{ route('websites.show', $latestJob->website) }}" class="mt-3 inline-block text-sm font-medium text-sky-700 hover:underline">View website &rarr;</a>
                @else
                    <div class="text-sm text-slate-400">
                        <p>No analysis has been submitted yet.</p>
                        <p class="mt-2">Create a <a class="text-sky-700 hover:underline" href="{{ route('projects.create') }}">project</a>, then add a website and request an analysis.</p>
                    </div>
                @endif
            </section>

            <section class="bg-white border border-slate-200 shadow-sm rounded-lg p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-3">Recent projects</h3>
                @forelse ($projects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="block py-2 border-b border-slate-100 last:border-0 text-sm text-slate-700 hover:text-sky-700">
                        {{ $project->name }}
                        <span class="text-slate-400">&middot; {{ $project->websites_count }} website{{ $project->websites_count === 1 ? '' : 's' }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">No projects yet.</p>
                @endforelse
            </section>
        </div>

        <section class="bg-white border border-slate-200 shadow-sm rounded-lg p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-2">Campaign readiness &amp; conversion opportunity</h3>
            <div class="text-sm text-slate-400">
                <p>No readiness or opportunity scores are available yet. These are produced by the Campaign Intelligence and Predictive Analytics modules (Phase 3), running on crawled website evidence.</p>
                <p class="mt-2">No numbers are shown until a real analysis has been stored.</p>
            </div>
        </section>

        <section class="bg-white border border-slate-200 shadow-sm rounded-lg p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-2">Module status</h3>
            <ul class="text-sm text-slate-600 space-y-1">
                <li>Foundation (auth, projects, websites, analysis jobs): <span class="font-medium text-emerald-700">active</span></li>
                <li>Website crawler and seven intelligence modules: <span class="font-medium text-slate-400">planned (Phase 2+)</span></li>
                <li>Budget calculators, reports and charts: <span class="font-medium text-slate-400">planned (Phase 2+)</span></li>
            </ul>
        </section>
    </div>
</x-app-layout>
