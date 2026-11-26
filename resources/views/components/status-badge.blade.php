@php
$classes = match ($status ?? '') {
    'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
    'failed', 'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200/80',
    'pending', 'dispatched', 'crawling', 'processing' => 'bg-amber-50 text-amber-700 border-amber-200/80',
    default => 'bg-slate-50 text-slate-600 border-slate-200',
};
$dot = match ($status ?? '') {
    'completed' => 'bg-emerald-500',
    'failed', 'cancelled' => 'bg-rose-500',
    'pending', 'dispatched', 'crawling', 'processing' => 'bg-amber-500',
    default => 'bg-slate-400',
};
@endphp
<span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {{ $classes }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span>
    {{ ucfirst($status ?? 'unknown') }}
</span>
