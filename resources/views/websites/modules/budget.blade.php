<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">Budget Intelligence &mdash; {{ $website->label ?: $website->host }}</h2>
        <p class="text-sm text-gray-400 mt-1">Deterministic budget simulations from assumptions you enter. These are never actual campaign performance figures.</p>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            @include('websites.partials.module-tabs', ['active' => 'budget', 'website' => $website])
        </div>

        <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50">
            <h3 class="text-lg font-medium text-white">Budget scenario calculator</h3>
            <p class="text-xs text-gray-500 mt-1">
                Every figure below is computed from the assumptions you type in (Formulas: clicks = budget / CPC, leads = clicks &times; lead rate, conversions = leads &times; conversion rate, CPA = budget / conversions, break-even ROAS = 1 / gross margin).
            </p>
            <form method="POST" action="{{ route('websites.budget-scenarios.store', $website) }}" class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                @csrf
                <div class="md:col-span-2">
                    <label for="label" class="block text-xs uppercase text-gray-400 mb-1">Scenario label</label>
                    <input id="label" name="label" type="text" maxlength="120" value="{{ old('label') }}"
                        class="w-full rounded-lg bg-slate-900/60 border-slate-700 text-gray-200 focus:ring-sky-500 focus:border-sky-500" placeholder="Q3 search push">
                </div>
                <div>
                    <label for="channel" class="block text-xs uppercase text-gray-400 mb-1">Channel assumption set</label>
                    <select id="channel" name="channel"
                        class="w-full rounded-lg bg-slate-900/60 border-slate-700 text-gray-200 focus:ring-sky-500 focus:border-sky-500">
                        <option value="all" @selected(old('channel', 'all') === 'all')>All channels (base assumptions)</option>
                        @foreach ($channels as $key => $meta)
                            <option value="{{ $key }}" @selected(old('channel') === $key)>
                                {{ $meta['label'] }} (CPC &times;{{ $meta['cpc_factor'] }}, rate &times;{{ $meta['rate_factor'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="budget" class="block text-xs uppercase text-gray-400 mb-1">Monthly budget (currency units)</label>
                    <input id="budget" name="budget" type="number" step="0.01" min="0.01" required value="{{ old('budget', $defaults['budget']) }}"
                        class="w-full rounded-lg bg-slate-900/60 border-slate-700 text-gray-200 focus:ring-sky-500 focus:border-sky-500">
                </div>
                <div>
                    <label for="cpc" class="block text-xs uppercase text-gray-400 mb-1">Assumed CPC</label>
                    <input id="cpc" name="cpc" type="number" step="0.01" min="0.01" max="1000" required value="{{ old('cpc', $defaults['cpc']) }}"
                        class="w-full rounded-lg bg-slate-900/60 border-slate-700 text-gray-200 focus:ring-sky-500 focus:border-sky-500">
                </div>
                <div>
                    <label for="lead_rate" class="block text-xs uppercase text-gray-400 mb-1">Assumed lead rate (%)</label>
                    <input id="lead_rate" name="lead_rate" type="number" step="0.01" min="0" max="100" required value="{{ old('lead_rate', $defaults['lead_rate']) }}"
                        class="w-full rounded-lg bg-slate-900/60 border-slate-700 text-gray-200 focus:ring-sky-500 focus:border-sky-500">
                </div>
                <div>
                    <label for="conversion_rate" class="block text-xs uppercase text-gray-400 mb-1">Lead to customer rate (%)</label>
                    <input id="conversion_rate" name="conversion_rate" type="number" step="0.01" min="0" max="100" required value="{{ old('conversion_rate', $defaults['conversion_rate']) }}"
                        class="w-full rounded-lg bg-slate-900/60 border-slate-700 text-gray-200 focus:ring-sky-500 focus:border-sky-500">
                </div>
                <div>
                    <label for="average_order_value" class="block text-xs uppercase text-gray-400 mb-1">Average order value</label>
                    <input id="average_order_value" name="average_order_value" type="number" step="0.01" min="0" required value="{{ old('average_order_value', $defaults['average_order_value']) }}"
                        class="w-full rounded-lg bg-slate-900/60 border-slate-700 text-gray-200 focus:ring-sky-500 focus:border-sky-500">
                </div>
                <div>
                    <label for="gross_margin" class="block text-xs uppercase text-gray-400 mb-1">Gross margin (%)</label>
                    <input id="gross_margin" name="gross_margin" type="number" step="0.01" min="0.01" max="100" required value="{{ old('gross_margin', $defaults['gross_margin']) }}"
                        class="w-full rounded-lg bg-slate-900/60 border-slate-700 text-gray-200 focus:ring-sky-500 focus:border-sky-500">
                </div>
                <div>
                    <label for="target_cpa" class="block text-xs uppercase text-gray-400 mb-1">Target CPA (optional)</label>
                    <input id="target_cpa" name="target_cpa" type="number" step="0.01" min="0" value="{{ old('target_cpa') }}"
                        class="w-full rounded-lg bg-slate-900/60 border-slate-700 text-gray-200 focus:ring-sky-500 focus:border-sky-500">
                </div>
                <div class="md:col-span-4 flex items-center gap-4">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition">
                        Save scenario
                    </button>
                    <p class="text-xs text-gray-500">Exactly one active scenario form is submitted per click; saved scenarios appear below.</p>
                </div>
            </form>
        </div>

        <div class="bg-slate-800/60 rounded-xl p-6 shadow border border-slate-700/50 space-y-6">
            <h3 class="text-lg font-medium text-white">Saved scenarios</h3>
            @forelse ($scenarios as $scenario)
                <div class="rounded-lg border border-slate-700/60 bg-slate-900/40 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm text-gray-200 font-medium">{{ $scenario->label }}</p>
                            <p class="text-xs text-gray-500">
                                channel: {{ $scenario->channel }} &middot; budget {{ number_format($scenario->inputs['budget']) }} &middot;
                                CPC {{ $scenario->inputs['cpc'] }} &middot; created {{ $scenario->created_at->format('Y-m-d H:i') }}
                            </p>
                        </div>
                        <form method="POST" action="{{ route('websites.budget-scenarios.destroy', [$website, $scenario]) }}"
                            onsubmit="return confirm('Delete this scenario?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-rose-800/60 text-rose-300 hover:bg-rose-900/30 transition">
                                Delete
                            </button>
                        </form>
                    </div>
                    <div class="overflow-x-auto mt-3">
                        <table class="min-w-full text-sm">
                            <thead class="text-xs uppercase text-gray-400 bg-slate-900/60">
                                <tr>
                                    <th class="px-3 py-2 text-left">Scenario</th>
                                    <th class="px-3 py-2 text-left">Clicks</th>
                                    <th class="px-3 py-2 text-left">Leads</th>
                                    <th class="px-3 py-2 text-left">Conversions</th>
                                    <th class="px-3 py-2 text-left">CPA</th>
                                    <th class="px-3 py-2 text-left">Revenue</th>
                                    <th class="px-3 py-2 text-left">ROAS</th>
                                    <th class="px-3 py-2 text-left">Break-even ROAS</th>
                                    @if ($scenario->inputs['target_cpa'] !== null)
                                        <th class="px-3 py-2 text-left">Meets target CPA</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/60">
                                @foreach ($scenario->results['scenarios'] as $row)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-200">{{ $row['scenario'] }}</td>
                                        <td class="px-3 py-2 text-gray-300">{{ number_format($row['estimated_clicks']) }}</td>
                                        <td class="px-3 py-2 text-gray-300">{{ number_format($row['estimated_leads'], 1) }}</td>
                                        <td class="px-3 py-2 text-gray-300">{{ number_format($row['estimated_conversions'], 1) }}</td>
                                        <td class="px-3 py-2 text-gray-300">{{ $row['estimated_cpa'] === null ? 'n/a' : number_format($row['estimated_cpa'], 2) }}</td>
                                        <td class="px-3 py-2 text-gray-300">{{ number_format($row['estimated_revenue']) }}</td>
                                        <td class="px-3 py-2 {{ ($row['meets_break_even_roas'] ?? false) ? 'text-emerald-300' : 'text-gray-300' }}">
                                            {{ $row['estimated_roas'] === null ? 'n/a' : number_format($row['estimated_roas'], 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-300">{{ $row['break_even_roas'] === null ? 'n/a' : number_format($row['break_even_roas'], 2) }}</td>
                                        @if ($scenario->inputs['target_cpa'] !== null)
                                            <td class="px-3 py-2">
                                                @if ($row['meets_target_cpa'] === null) <span class="text-gray-500">n/a</span>
                                                @elseif ($row['meets_target_cpa']) <span class="text-emerald-300">yes</span>
                                                @else <span class="text-rose-300">no</span> @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Deterministic simulation from stated assumptions &mdash; not actual campaign performance. Version {{ $scenario->version }}.</p>
                </div>
            @empty
                <p class="text-sm text-gray-400">No saved scenarios yet. Enter assumptions above and save your first scenario.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
