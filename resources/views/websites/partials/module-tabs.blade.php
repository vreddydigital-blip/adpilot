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
<nav class="bg-white rounded-xl border border-slate-200/80 shadow-sm shadow-slate-900/[0.03] px-2 overflow-x-auto" aria-label="Intelligence modules">
    <div class="flex min-w-max">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route($tab['route'], $website) }}"
               @if ($active === $key) aria-current="page" @endif
               class="group relative flex items-baseline gap-2 px-4 py-3.5 text-sm whitespace-nowrap transition {{ $active === $key ? 'text-indigo-700 font-semibold' : 'text-slate-500 font-medium hover:text-slate-900' }}">
                <span class="font-mono text-[10px] {{ $active === $key ? 'text-indigo-500' : 'text-slate-400' }}">{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
                {{ $tab['label'] }}
                <span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full {{ $active === $key ? 'bg-indigo-600' : 'bg-transparent group-hover:bg-slate-200' }} transition"></span>
            </a>
        @endforeach
    </div>
</nav>
