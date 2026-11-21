<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-slate-900 leading-tight">{{ $project->name }}</h2>
                @if ($project->description)
                    <p class="mt-1 text-sm text-slate-500">{{ $project->description }}</p>
                @endif
            </div>
            <a href="{{ route('projects.edit', $project) }}" class="text-sm font-medium text-sky-700 hover:underline">Edit project</a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-6">
        <section class="bg-white border border-slate-200 shadow-sm rounded-lg p-6">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Add a website</h3>
            <form method="POST" action="{{ route('websites.store') }}" class="sm:flex sm:items-start sm:gap-3 space-y-3 sm:space-y-0">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">
                <div class="flex-1 w-full">
                    <label for="url" class="sr-only">Website URL</label>
                    <input id="url" name="url" type="text" value="{{ old('url') }}" placeholder="https://example.com" required
                           class="block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600">
                    @error('url') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="w-full sm:w-48">
                    <label for="label" class="sr-only">Label (optional)</label>
                    <input id="label" name="label" type="text" value="{{ old('label') }}" placeholder="Label (optional)" maxlength="150"
                           class="block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600">
                </div>
                <button type="submit" class="inline-flex items-center rounded-md bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800">Add website</button>
            </form>
            <p class="mt-2 text-xs text-slate-400">Only public http/https addresses are accepted. localhost, private and metadata addresses are rejected.</p>
        </section>

        <section class="bg-white border border-slate-200 shadow-sm rounded-lg">
            <h3 class="text-sm font-semibold text-slate-900 px-6 pt-5">Websites</h3>
            @if ($websites->isEmpty())
                <div class="p-6 text-sm text-slate-400">No websites in this project yet. Add one above to start an analysis.</div>
            @else
                <ul class="divide-y divide-slate-100 mt-3">
                    @foreach ($websites as $website)
                        <li class="p-5 flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <a href="{{ route('websites.show', $website) }}" class="text-sm font-medium text-slate-900 hover:text-sky-700">{{ $website->host }}</a>
                                @if ($website->label)
                                    <span class="text-xs text-slate-400">&middot; {{ $website->label }}</span>
                                @endif
                                <p class="mt-1 text-xs text-slate-400 truncate">{{ $website->normalized_url }}</p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                @if ($website->latestAnalysisJob)
                                    <x-status-badge :status="$website->latestAnalysisJob->status" />
                                @else
                                    <span class="text-xs text-slate-400">Not analysed</span>
                                @endif
                                <form method="POST" action="{{ route('websites.destroy', $website) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-slate-400 hover:text-rose-700">Remove</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-app-layout>
