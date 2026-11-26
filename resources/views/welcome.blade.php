<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'AdPilot AI') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased bg-[#f7f8fa]">
        <div class="min-h-screen flex flex-col [background-image:radial-gradient(circle,#e2e5ec_1px,transparent_1px)] [background-size:22px_22px]">

            <header class="max-w-6xl w-full mx-auto flex items-center justify-between px-5 py-5">
                <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white grid place-items-center text-base font-bold shadow-md shadow-indigo-600/25">A</span>
                    <span class="text-lg font-semibold tracking-tight">AdPilot <span class="text-indigo-600">AI</span></span>
                </a>

                <nav class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-indigo-600/25 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Open workspace</a>
                    @endauth
                    @guest
                        <a href="{{ route('login') }}" class="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-white hover:shadow-sm">Sign in</a>
                        <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-indigo-600/25 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Create account</a>
                    @endguest
                </nav>
            </header>

            <main class="flex-1">
                <section class="max-w-6xl w-full mx-auto px-5 pt-14 pb-16 sm:pt-20">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-indigo-600 mb-4">Conversion readiness, from evidence</p>
                    <h1 class="text-4xl sm:text-5xl font-bold tracking-tight leading-[1.1] max-w-2xl">
                        Audit your website.<br>
                        <span class="text-slate-400">Know exactly what to fix before you spend on ads.</span>
                    </h1>
                    <p class="mt-5 text-base sm:text-lg text-slate-500 max-w-xl leading-relaxed">
                        AdPilot AI crawls your site, evaluates conversion readiness across seven intelligence modules, and returns evidence-based recommendations. No dashboards full of invented numbers &mdash; every score shows its methodology.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        @guest
                            <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/25 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Start free &rarr;</a>
                            <a href="{{ route('login') }}" class="inline-flex items-center rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 border border-slate-200 shadow-sm hover:bg-slate-50">Sign in</a>
                        @endguest
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/25 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Open workspace &rarr;</a>
                        @endauth
                        <span class="text-xs text-slate-400 ml-1">No payment card. Heuristics only until ad platforms are integrated.</span>
                    </div>
                </section>

                <section class="max-w-6xl w-full mx-auto px-5 pb-20">
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xl shadow-slate-900/[0.06] overflow-hidden">
                        <div class="grid sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 divide-slate-100 sm:[&>*:nth-child(even)]:border-l lg:[&>*:nth-child(n+3)]:border-t lg:[&>*:nth-child(n+3)]:border-l">
                            @foreach ([
                                ['01', 'Campaign readiness', 'Seven weighted readiness criteria, scored and evidenced.'],
                                ['02', 'Technical audit', 'Eight crawl-based checks covering conversion blockers.'],
                                ['03', 'Conversion opportunity', 'Ranked opportunities with severity, effort and confidence.'],
                                ['04', 'Budget intelligence', 'Deterministic what-if simulations, no fabricated metrics.'],
                                ['05', 'Wasted spend signals', 'Structural waste findings with corrections and impact.'],
                                ['06', 'Lead intelligence', 'Form and contact-path readiness parsed from real pages.'],
                                ['07', 'Creative intelligence', 'Ad-ready headlines and descriptions drawn from your copy.'],
                                ['08', 'AI advisor', 'Rule-based recommendations consolidating all modules.'],
                            ] as [$index, $title, $desc])
                                <div class="p-6">
                                    <p class="font-mono text-xs text-indigo-400 mb-3">{{ $index }}</p>
                                    <p class="text-sm font-semibold tracking-tight">{{ $title }}</p>
                                    <p class="mt-1.5 text-xs text-slate-500 leading-relaxed">{{ $desc }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            </main>

            <footer class="max-w-6xl w-full mx-auto px-5 pb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-slate-400">
                <span>&copy; {{ date('Y') }} {{ config('app.name', 'AdPilot AI') }}</span>
                <span>Heuristic intelligence demo build &middot; real ad metrics require integrations</span>
            </footer>
        </div>
    </body>
</html>
