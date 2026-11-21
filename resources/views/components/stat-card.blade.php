<div class="bg-white overflow-hidden shadow-sm rounded-lg border border-slate-200">
    <div class="p-5">
        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $value }}</p>
        @isset($hint)
            <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
        @endisset
    </div>
</div>
