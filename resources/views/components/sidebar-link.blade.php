<a {{ $attributes->merge(['class' => 'flex items-center px-3 py-2 rounded-md text-sm font-medium transition '.(($active ?? false) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white')]) }}>
    {{ $slot }}
</a>
