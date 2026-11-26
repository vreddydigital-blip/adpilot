<div class="bg-white rounded-xl border border-slate-200/80 shadow-sm shadow-slate-900/[0.03] overflow-hidden">
    <div class="p-5">
        <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">{{ $label }}</p>
        <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 tabular-nums">{{ $value }}</p>
        @isset($hint)
            <p class="mt-1.5 text-xs text-slate-400">{{ $hint }}</p>
        @endisset
    </div>
</div>
