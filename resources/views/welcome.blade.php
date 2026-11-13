<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tantou (担当) — Human Capacity OS</title>
        <meta name="description" content="A visual, interactive workload marketplace where tasks are managed via a live Drag-and-Drop canvas with capacity transparency and automated gamification.">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-indigo-500 selection:text-white antialiased overflow-x-hidden">
        {{-- Background Glow Gradients --}}
        <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
            <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[72rem] h-[36rem] bg-gradient-to-b from-indigo-600/20 via-purple-600/10 to-transparent blur-3xl opacity-70"></div>
            <div class="absolute top-1/3 -left-40 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl"></div>
            <div class="absolute top-2/3 -right-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl"></div>
        </div>

        {{-- Top Navigation Bar --}}
        <header class="border-b border-slate-800/80 bg-slate-950/70 backdrop-blur-xl sticky top-0 z-40 px-6 py-4">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <a href="{{ route('landing') }}" class="flex items-center space-x-3.5 group">
                    <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-amber-400 flex items-center justify-center font-bold text-lg text-white shadow-lg shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                        担
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xl font-bold tracking-tight text-white">Tantou <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">担当</span></span>
                        </div>
                        <p class="text-[11px] text-slate-400">Human Capacity OS</p>
                    </div>
                </a>

                <div class="flex items-center gap-3">
                    @auth
                        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs">
                            <span class="font-medium text-slate-300">{{ auth()->user()->name }}</span>
                            <span class="text-amber-400 font-semibold">★ {{ auth()->user()->total_points }} pts</span>
                        </div>
                        <a
                            href="{{ route('board') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/25 transition-all"
                        >
                            Open Canvas
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="px-3 py-2 text-xs font-medium text-slate-400 hover:text-rose-400 transition-colors cursor-pointer"
                            >
                                Sign Out
                            </button>
                        </form>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-900 border border-transparent hover:border-slate-800 transition-all"
                        >
                            Sign In
                        </a>
                        <a
                            href="{{ route('register') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white text-xs font-semibold shadow-md shadow-indigo-600/30 transition-all"
                        >
                            Create Workspace
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        {{-- Hero Section --}}
        <section class="max-w-7xl mx-auto px-6 pt-16 pb-20 text-center">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-medium mb-8">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>The Workload Marketplace • Inversion of Control</span>
            </div>

            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white max-w-4xl mx-auto leading-tight">
                Stop routing tickets.<br>
                <span class="bg-gradient-to-r from-indigo-400 via-purple-300 to-amber-300 bg-clip-text text-transparent">
                    Visualize real team capacity.
                </span>
            </h1>

            <p class="mt-6 text-lg sm:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
                Tantou is NOT a traditional helpdesk. It is an interactive Human Capacity OS where tasks are managed via a live Drag-and-Drop canvas with real-time capacity transparency and automated gamified bounty rewards.
            </p>

            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                @auth
                    <a
                        href="{{ route('board') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold shadow-xl shadow-indigo-600/30 text-sm transition-all"
                    >
                        <span>Launch Your Board ({{ auth()->user()->tenant->name ?? 'Demo' }})</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </a>
                @else
                    <a
                        href="{{ route('register') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold shadow-xl shadow-indigo-600/30 text-sm transition-all"
                    >
                        <span>Create Free Workspace</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </a>
                    <a
                        href="{{ route('login') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-2xl bg-slate-900/80 hover:bg-slate-800 text-slate-200 border border-slate-800 font-semibold text-sm transition-all"
                    >
                        <span>Sign In / Demo Login</span>
                    </a>
                @endauth
            </div>

            {{-- Live Canvas Preview Card --}}
            <div class="mt-16 max-w-5xl mx-auto rounded-3xl p-1 bg-gradient-to-b from-indigo-500/30 via-slate-800/40 to-slate-900/60 shadow-2xl">
                <div class="bg-slate-950/90 rounded-[22px] p-6 sm:p-8 backdrop-blur-2xl border border-slate-800/80 text-left">
                    <div class="flex items-center justify-between pb-6 border-b border-slate-800/80">
                        <div class="flex items-center gap-3">
                            <span class="h-3 w-3 rounded-full bg-rose-500/80 inline-block"></span>
                            <span class="h-3 w-3 rounded-full bg-amber-500/80 inline-block"></span>
                            <span class="h-3 w-3 rounded-full bg-emerald-500/80 inline-block"></span>
                            <span class="text-xs text-slate-400 font-mono ml-2">Tantou Live Canvas • Hellenic Demo Ops</span>
                        </div>
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-emerald-950/60 border border-emerald-800/40 text-emerald-400 text-[11px] font-medium">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            WebSockets Connected (Reverb)
                        </div>
                    </div>

                    {{-- Mock 3-Column Board Snapshot --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-6">
                        {{-- Backlog Preview --}}
                        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/60 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Unassigned Backlog</span>
                                <span class="text-xs bg-slate-800 px-2 py-0.5 rounded-full text-slate-400 font-medium">3</span>
                            </div>
                            <div class="space-y-3">
                                <div class="p-3.5 rounded-xl bg-slate-950/80 border border-indigo-500/30 shadow-sm">
                                    <div class="flex items-center justify-between text-[11px] mb-1.5">
                                        <span class="px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 font-semibold border border-blue-500/20">Software</span>
                                        <span class="px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 font-bold text-[10px]">URGENT</span>
                                    </div>
                                    <h4 class="text-xs font-semibold text-white">Stripe billing webhooks</h4>
                                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-900 text-[11px] text-slate-400">
                                        <span>Weight: 3</span>
                                        <span class="text-amber-400 font-semibold">★ 38 pts</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Active Worker Column Preview (Nikos) --}}
                        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/60 p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-6 w-6 rounded-lg bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-bold text-xs">N</div>
                                    <span class="text-xs font-bold text-white">Nikos Pappas</span>
                                </div>
                                <span class="text-[11px] font-semibold text-rose-400">9/10 Cap</span>
                            </div>
                            {{-- High load progress bar --}}
                            <div class="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden mb-3">
                                <div class="h-full bg-gradient-to-r from-amber-500 to-rose-500 w-[90%] rounded-full"></div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800">
                                <div class="flex items-center justify-between text-[11px] mb-1.5">
                                    <span class="px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 font-semibold border border-blue-500/20">Software</span>
                                    <span class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 font-medium text-[10px]">Co-Op 30%</span>
                                </div>
                                <h4 class="text-xs font-semibold text-white">Resolve MariaDB deadlocks</h4>
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-900 text-[11px] text-slate-400">
                                    <span>Helper: Kostas</span>
                                    <span class="text-amber-400 font-semibold">★ 50 pts</span>
                                </div>
                            </div>
                        </div>

                        {{-- Completed Column Preview --}}
                        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/60 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Completed / Done</span>
                                <span class="text-xs bg-emerald-950/60 border border-emerald-800/40 px-2 py-0.5 rounded-full text-emerald-300 font-medium">2</span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-950/80 border border-emerald-900/30">
                                <div class="flex items-center justify-between text-[11px] mb-1.5">
                                    <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-semibold border border-emerald-500/20">Υποδομές</span>
                                    <span class="text-emerald-400 font-bold">✓ DONE</span>
                                </div>
                                <h4 class="text-xs font-semibold text-slate-300">Scaffold TALL stack & Docker</h4>
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-900 text-[11px] text-slate-400">
                                    <span>Tantou: Nikos</span>
                                    <span class="text-emerald-400 font-semibold">+38 pts</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Core Value Pillars (3-Column Grid) --}}
        <section class="max-w-7xl mx-auto px-6 py-20 border-t border-slate-800/80">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold tracking-tight text-white">Why Modern Teams Switch to Tantou</h2>
                <p class="mt-3 text-sm text-slate-400">Radical transparency eliminates the friction of traditional ticketing queues.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                {{-- Pillar 1 --}}
                <div class="p-8 rounded-3xl bg-slate-900/60 border border-slate-800/80 hover:border-indigo-500/40 transition-all group">
                    <div class="h-12 w-12 rounded-2xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                        ⚡
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Total Capacity Transparency</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        Requesters see actual worker load before dropping tickets. Visual capacity gauges warn when a teammate is nearing cognitive burnout.
                    </p>
                </div>

                {{-- Pillar 2 --}}
                <div class="p-8 rounded-3xl bg-slate-900/60 border border-slate-800/80 hover:border-amber-500/40 transition-all group">
                    <div class="h-12 w-12 rounded-2xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                        🎯
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Direct Tantou Selection</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        No middle managers routing tickets. Requesters drop post-its directly onto the chosen collaborator, or workers pull unassigned tasks from the backlog.
                    </p>
                </div>

                {{-- Pillar 3 --}}
                <div class="p-8 rounded-3xl bg-slate-900/60 border border-slate-800/80 hover:border-emerald-500/40 transition-all group">
                    <div class="h-12 w-12 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                        🏆
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Automated Bounty & Co-Op</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        Points calculate automatically from cognitive load, urgency priority multipliers, and historical durations. Co-op helpers earn a fair split (up to 50%).
                    </p>
                </div>
            </div>
        </section>

        {{-- Footer --}}
        <footer class="border-t border-slate-800/80 bg-slate-950 py-10 px-6 text-center text-xs text-slate-500">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-300">Tantou (担当)</span>
                    <span>•</span>
                    <span>Human Capacity OS & Workload Marketplace</span>
                </div>
                <div class="flex items-center gap-4 text-slate-400 font-mono text-[11px]">
                    <span>Laravel 13</span>
                    <span>•</span>
                    <span>Livewire 3</span>
                    <span>•</span>
                    <span>Alpine.js</span>
                    <span>•</span>
                    <span>Reverb</span>
                </div>
            </div>
        </footer>
    </body>
</html>
