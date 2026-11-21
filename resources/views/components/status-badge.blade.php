@php
$classes = match ($status ?? '') {
    'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'failed', 'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
    'pending', 'dispatched', 'crawling', 'processing' => 'bg-amber-100 text-amber-800 border-amber-200',
    default => 'bg-slate-100 text-slate-700 border-slate-200',
};
@endphp
<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $classes }}">
    {{ ucfirst($status ?? 'unknown') }}
</span>
