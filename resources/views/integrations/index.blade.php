<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">Integrations</h2>
        <p class="mt-1 text-sm text-slate-500">Prepare provider connections for future live data. Nothing is connected today.</p>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-6">
        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $note }}
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($providers as $p)
                <section class="bg-white border border-slate-200 shadow-sm rounded-lg p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">{{ $p['label'] }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $p['description'] }}</p>
                        </div>
                        @if ($p['credentials_saved'])
                            <span class="shrink-0 rounded-full bg-sky-100 text-sky-800 px-2.5 py-0.5 text-xs font-medium">Credentials saved</span>
                        @else
                            <span class="shrink-0 rounded-full bg-slate-100 text-slate-600 px-2.5 py-0.5 text-xs font-medium">Not connected</span>
                        @endif
                    </div>

                    <p class="mt-3 text-xs text-slate-500">
                        Would provide: {{ implode(', ', $p['provides']) }} &mdash; none of these values are available while the connection is not live.
                    </p>

                    @if ($p['credentials_saved'])
                        <p class="mt-2 text-xs text-slate-400">Saved fields: {{ implode(', ', $p['saved_fields']) }} (values hidden, stored encrypted).</p>
                    @endif

                    <form method="POST" action="{{ route('integrations.save', $p['provider']) }}" class="mt-4 space-y-3">
                        @csrf
                        @foreach ($p['credentials'] as $field)
                            <div>
                                <label for="{{ $p['provider'] }}-{{ $field }}" class="block text-xs font-medium text-slate-600">{{ $field }}</label>
                                <input id="{{ $p['provider'] }}-{{ $field }}" name="credentials[{{ $field }}]" type="password" autocomplete="off"
                                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm"
                                       value="" @if(in_array($field, $p['saved_fields'])) placeholder="stored - leave blank to keep" @endif>
                            </div>
                        @endforeach
                        <div class="flex items-center gap-3">
                            <button type="submit" class="inline-flex items-center rounded-md bg-sky-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-800">Save credentials</button>
                        </div>
                    </form>

                    @if ($p['credentials_saved'])
                        <form method="POST" action="{{ route('integrations.destroy', $p['provider']) }}" class="mt-2"
                              onsubmit="return confirm('Remove the stored credentials for {{ $p['label'] }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-700 hover:underline">Remove stored credentials</button>
                        </form>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
