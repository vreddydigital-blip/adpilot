<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' - AdPilot AI' : 'AdPilot AI' }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[#f7f8fa] text-slate-900">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen flex">
            <aside
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="lg:translate-x-0 fixed lg:sticky inset-y-0 left-0 z-40 w-64 shrink-0 bg-white border-r border-slate-200/80 transition-transform duration-200 lg:flex lg:flex-col lg:top-0 lg:h-screen"
            >
                <div class="h-16 flex items-center gap-3 px-6 border-b border-slate-100">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 group">
                        <span class="w-8 h-8 rounded-lg bg-indigo-600 text-white grid place-items-center text-sm font-bold shadow-sm shadow-indigo-600/30 group-hover:bg-indigo-500 transition">A</span>
                        <span class="text-[15px] font-semibold tracking-tight text-slate-900">AdPilot <span class="text-indigo-600">AI</span></span>
                    </a>
                </div>
                <nav class="flex-1 p-4 space-y-1">
                    <p class="px-3 pt-1 pb-2 text-[11px] font-semibold uppercase tracking-widest text-slate-400">Workspace</p>
                    <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Overview</x-sidebar-link>
                    <x-sidebar-link :href="route('projects.index')" :active="request()->routeIs('projects.*')">Projects</x-sidebar-link>
                    <x-sidebar-link :href="route('integrations.index')" :active="request()->routeIs('integrations.*')">Integrations</x-sidebar-link>
                    <p class="px-3 pt-4 pb-2 text-[11px] font-semibold uppercase tracking-widest text-slate-400">Account</p>
                    <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Settings</x-sidebar-link>
                </nav>
                <div class="px-6 py-4 border-t border-slate-100">
                    <p class="text-[11px] leading-relaxed text-slate-400">
                        Heuristic intelligence demo build.<br>Real ad metrics require integrations.
                    </p>
                </div>
            </aside>

            <div x-show="sidebarOpen" x-on:click="sidebarOpen = false" class="lg:hidden fixed inset-0 z-30 bg-slate-900/40" x-cloak></div>

            <div class="flex-1 min-w-0">
                <div class="lg:hidden sticky top-0 z-20 bg-white/90 backdrop-blur border-b border-slate-200/80 h-14 flex items-center px-4">
                    <button x-on:click="sidebarOpen = true" class="p-2 -ms-2 rounded-md text-slate-600 hover:bg-slate-100" aria-label="Open menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <span class="ms-2 flex items-center gap-2 font-semibold tracking-tight">
                        <span class="w-7 h-7 rounded-md bg-indigo-600 text-white grid place-items-center text-xs font-bold">A</span>
                        AdPilot <span class="text-indigo-600">AI</span>
                    </span>
                </div>

                <header class="sticky top-0 z-20 bg-[#f7f8fa]/85 backdrop-blur">
                    <div class="max-w-6xl mx-auto h-14 px-4 sm:px-6 flex items-center justify-end">
                        <x-dropdown align="right" width="48" content-classes="py-1 bg-white">
                            <x-slot name="trigger">
                                <button class="flex items-center gap-2.5 rounded-full focus:outline-none group">
                                    <span class="w-8 h-8 rounded-full bg-indigo-50 border border-indigo-200/70 text-indigo-700 grid place-items-center text-xs font-bold group-hover:bg-indigo-100 transition">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                                    <span class="hidden sm:block text-sm font-medium text-slate-700 group-hover:text-slate-900 transition">{{ auth()->user()->name }}</span>
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">Settings</x-dropdown-link>
                                <form method="POST" action="{{ route('logout') }}" x-data>
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                        @click.prevent="$root.submit();">
                                        Log out
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </header>
                @if (session('status'))
                    <div class="max-w-6xl mx-auto mt-5 px-4 sm:px-6">
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200/80 p-3.5 text-sm text-emerald-800 shadow-sm shadow-emerald-900/5">
                            {{ session('status') }}
                        </div>
                    </div>
                @endif

                @isset($header)
                    <header class="max-w-6xl mx-auto px-4 sm:px-6 pt-8">
                        {{ $header }}
                    </header>
                @endisset

                <main class="pb-16">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
