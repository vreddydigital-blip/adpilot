<a {{ $attributes->merge(['class' => 'group flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition '.(($active ?? false) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900')]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ ($active ?? false) ? 'bg-indigo-600' : 'bg-slate-300 group-hover:bg-slate-400' }} transition"></span>
    {{ $slot }}
</a>
