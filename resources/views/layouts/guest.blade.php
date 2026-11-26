<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'AdPilot AI') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased bg-[#f7f8fa]">
        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10 [background-image:radial-gradient(circle,#e2e5ec_1px,transparent_1px)] [background-size:22px_22px]">
            <a href="/" class="flex items-center gap-2.5 mb-7">
                <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white grid place-items-center text-base font-bold shadow-md shadow-indigo-600/25">A</span>
                <span class="text-lg font-semibold tracking-tight">AdPilot <span class="text-indigo-600">AI</span></span>
            </a>

            <div class="w-full sm:max-w-md bg-white rounded-2xl border border-slate-200/80 shadow-xl shadow-slate-900/[0.06] px-7 py-7">
                {{ $slot }}
            </div>

            <p class="mt-6 text-xs text-slate-400">Heuristic intelligence demo build &middot; real ad metrics require integrations</p>
        </div>
    </body>
</html>
