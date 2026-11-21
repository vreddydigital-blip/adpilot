<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' - AdPilot AI' : 'AdPilot AI' }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-100">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">
            <div class="flex min-h-screen">
                <aside
                    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                    class="lg:hidden fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 text-slate-100 transition-transform duration-200 lg:hidden"
                >
                    <div class="h-16 flex items-center px-6 border-b border-slate-800">
                        <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-tight text-white">
                            AdPilot <span class="text-sky-400">AI</span>
                        </a>
                    </div>
                    <nav class="p-4 space-y-1">
                        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Overview</x-sidebar-link>
                        <x-sidebar-link :href="route('projects.index')" :active="request()->routeIs('projects.*')">Projects</x-sidebar-link>
                        <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Settings</x-sidebar-link>
                    </nav>
                    <div class="px-6 py-4 text-xs text-slate-400 border-t border-slate-800 mt-4">
                        Portfolio build · Phase 4 (AI Advisor in Phase 5)
                    </div>
                </aside>

                <div x-show="sidebarOpen" x-on:click="sidebarOpen = false" class="lg:hidden fixed inset-0 z-30 bg-slate-900/60" x-cloak></div>

                <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:shrink-0 bg-slate-900 text-slate-100">
                    <div class="h-16 flex items-center px-6 border-b border-slate-800">
                        <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-tight text-white">
                            AdPilot <span class="text-sky-400">AI</span>
                        </a>
                    </div>
                    <nav class="p-4 space-y-1">
                        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Overview</x-sidebar-link>
                        <x-sidebar-link :href="route('projects.index')" :active="request()->routeIs('projects.*')">Projects</x-sidebar-link>
                        <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Settings</x-sidebar-link>
                    </nav>
                    <div class="px-6 py-4 text-xs text-slate-400 border-t border-slate-800 mt-auto">
                        Portfolio build · Phase 4 (AI Advisor in Phase 5)
                    </div>
                </aside>

                <div class="flex-1 min-w-0">
                    <div class="lg:hidden sticky top-0 z-20 bg-slate-900 text-white h-14 flex items-center px-4">
                        <button x-on:click="sidebarOpen = true" class="p-2 rounded-md hover:bg-slate-800" aria-label="Open menu">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <span class="ms-3 font-bold">AdPilot <span class="text-sky-400">AI</span></span>
                    </div>

                    @if (session('status'))
                        <div class="max-w-5xl mx-auto mt-6 px-4 sm:px-6">
                            <div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">
                                {{ session('status') }}
                            </div>
                        </div>
                    @endif

                    @isset($header)
                        <header>
                            <div class="max-w-5xl mx-auto py-6 px-4 sm:px-6">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    <main class="pb-12 pt-4">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
