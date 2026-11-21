<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">Edit project: {{ $project->name }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="bg-white border border-slate-200 shadow-sm rounded-lg p-6">
            <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $project->name) }}" required maxlength="120"
                           class="mt-1 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600">
                    @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">Description <span class="text-slate-400">(optional)</span></label>
                    <textarea id="description" name="description" rows="3" maxlength="2000"
                              class="mt-1 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600">{{ old('description', $project->description) }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex items-center rounded-md bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800">Save changes</button>
                    <a href="{{ route('projects.show', $project) }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
