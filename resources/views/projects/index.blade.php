<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-900 leading-tight">Projects</h2>
            <a href="{{ route('projects.create') }}" class="inline-flex items-center rounded-md bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800">New project</a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6">
        @if ($projects->isEmpty())
            <div class="bg-white border border-slate-200 shadow-sm rounded-lg p-10 text-center">
                <h3 class="text-base font-semibold text-slate-900">No projects yet</h3>
                <p class="mt-2 text-sm text-slate-500">A project groups the websites you want to analyse.</p>
                <a href="{{ route('projects.create') }}" class="mt-4 inline-block text-sm font-medium text-sky-700 hover:underline">Create your first project</a>
            </div>
        @else
            <div class="bg-white border border-slate-200 shadow-sm rounded-lg divide-y divide-slate-100">
                @foreach ($projects as $project)
                    <div class="p-5 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <a href="{{ route('projects.show', $project) }}" class="text-sm font-semibold text-slate-900 hover:text-sky-700">{{ $project->name }}</a>
                            <p class="mt-1 text-xs text-slate-400">{{ $project->websites_count }} website{{ $project->websites_count === 1 ? '' : 's' }}@if($project->description) &middot; {{ \Illuminate\Support\Str::limit($project->description, 80) }}@endif</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <a href="{{ route('projects.edit', $project) }}" class="text-sm text-slate-500 hover:text-slate-800">Edit</a>
                            <form method="POST" action="{{ route('projects.destroy', $project) }}">
                                @csrf
                                @method('DELETE')
                                <x-danger-button type="submit">Delete</x-danger-button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
