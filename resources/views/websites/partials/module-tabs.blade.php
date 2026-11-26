@php
    $tabs = [
        'campaign' => ['label' => 'Campaign Intelligence', 'route' => 'websites.show'],
        'predictive' => ['label' => 'Predictive Analytics', 'route' => 'websites.predictive'],
        'budget' => ['label' => 'Budget Intelligence', 'route' => 'websites.budget'],
        'wasted' => ['label' => 'Wasted Spend', 'route' => 'websites.wasted-spend'],
        'lead' => ['label' => 'Lead Intelligence', 'route' => 'websites.lead'],
        'creative' => ['label' => 'Ad Creative', 'route' => 'websites.creative'],
        'advisor' => ['label' => 'AI Advisor', 'route' => 'websites.advisor'],
        'reports' => ['label' => 'Reports', 'route' => 'websites.reports'],
    ];
@endphp
<nav class="flex flex-wrap gap-2" aria-label="Intelligence modules">
    @foreach ($tabs as $key => $tab)
        <a href="{{ route($tab['route'], $website) }}"
           @if ($active === $key) aria-current="page" @endif
           class="px-3 py-1.5 rounded-lg text-sm font-medium border transition {{ $active === $key ? 'bg-sky-600 border-sky-500 text-white' : 'bg-slate-800/60 border-slate-700 text-gray-300 hover:bg-slate-700/60' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
