<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tantou (担当) — {{ __('The Open-Core Human Capacity OS') }}</title>
        <meta name="description" content="{{ __('An interactive workload marketplace where tasks are managed via a live Drag-and-Drop canvas with real-time capacity transparency, smart queues, and automated gamification.') }}">
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
                        <p class="text-[11px] text-slate-400">{{ __('Human Capacity OS') }}</p>
                    </div>
                </a>

                {{-- Desktop Nav Links --}}
                <nav class="hidden md:flex items-center gap-6 text-xs font-medium text-slate-300">
                    <a href="#features" class="hover:text-white transition-colors">{{ __('Features') }}</a>
                    <a href="#architectures" class="hover:text-white transition-colors">{{ __('Architectures') }}</a>
                    @if (!\App\Support\Edition::hideMarketing())
                        <a href="#pricing" class="hover:text-white transition-colors">{{ __('Pricing') }}</a>
                    @endif
                    <a href="#self-host" class="hover:text-white transition-colors">{{ __('Self-Host') }}</a>
                </nav>

                <div class="flex items-center gap-3">
                    <x-language-switcher />

                    @auth
                        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs">
                            <span class="font-medium text-slate-300">{{ auth()->user()->name }}</span>
                            <span class="text-amber-400 font-semibold">★ {{ auth()->user()->total_points }} {{ __('pts') }}</span>
                        </div>
                        <a
                            href="{{ route('board') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/25 transition-all"
                        >
                            {{ __('Open Canvas') }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="px-3 py-2 text-xs font-medium text-slate-400 hover:text-rose-400 transition-colors cursor-pointer"
                            >
                                {{ __('Sign Out') }}
                            </button>
                        </form>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-900 border border-transparent hover:border-slate-800 transition-all"
                        >
                            {{ __('Sign In') }}
                        </a>
                        @if(\App\Support\Edition::canRegisterNewTenant())
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white text-xs font-semibold shadow-md shadow-indigo-600/30 transition-all"
                            >
                                {{ __('Create Workspace') }}
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        {{-- SECTION 1: HERO & INSTANT ACTIONS --}}
        <section class="max-w-7xl mx-auto px-6 pt-16 pb-20 text-center">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-medium mb-8">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>{{ __('Open-Core Human Capacity OS • Inversion of Control') }}</span>
            </div>

            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white max-w-4xl mx-auto leading-tight">
                {{ __('The Open-Core Human Capacity OS') }}<br>
                <span class="bg-gradient-to-r from-indigo-400 via-purple-300 to-amber-300 bg-clip-text text-transparent">
                    {{ __('for Real-Time Operations') }}
                </span>
            </h1>

            <p class="mt-6 text-lg sm:text-xl text-slate-400 max-w-3xl mx-auto leading-relaxed">
                {{ __('Manage finite human cognitive load with visual W1–W5 capacity gauges, smart FIFO + Priority queue dispatch, 3-tier visibility firewalls, and instant dual-transport execution across Web Canvas & 2-Way Telegram.') }}
            </p>

            @if(\App\Support\Edition::isSelfHosted() && \App\Models\Tenant::count() === 0)
                <div class="mt-8">
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-semibold shadow-xl shadow-emerald-600/30 text-sm transition-all"
                    >
                        <span>{{ __('Complete Initial Organization Setup') }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </a>
                </div>
            @endif

            {{-- 3 Primary CTAs --}}
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a
                    href="{{ route('login') }}"
                    class="w-full sm:w-auto inline-flex flex-col items-center justify-center px-8 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold shadow-xl shadow-indigo-600/30 text-sm transition-all"
                >
                    <span class="flex items-center gap-2">
                        <span>🚀</span>
                        <span>{{ __('Try Live Interactive Demo') }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </span>
                    <span class="text-[11px] text-indigo-200 font-normal mt-0.5">
                        {{ __('No signup required • Resets every 2 hours') }}
                    </span>
                </a>

                @if(!\App\Support\Edition::hideMarketing() && \App\Support\Edition::canRegisterNewTenant())
                    <a
                        href="{{ route('register') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-purple-600 hover:bg-purple-500 active:bg-purple-700 text-white font-semibold shadow-xl shadow-purple-600/30 text-sm transition-all"
                    >
                        <span>☁️</span>
                        <span>{{ __('Launch Cloud Workspace') }}</span>
                    </a>
                @elseif(!\App\Support\Edition::hideMarketing())
                    <a
                        href="#pricing"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-slate-900 hover:bg-slate-800 text-slate-200 border border-slate-700 font-semibold text-sm transition-all"
                    >
                        <span>☁️</span>
                        <span>{{ __('Launch Cloud Workspace') }}</span>
                    </a>
                @endif

                <a
                    href="#self-host"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-slate-900/80 hover:bg-slate-800 text-slate-200 border border-slate-800 font-semibold text-sm transition-all"
                >
                    <span>🐳</span>
                    <span>{{ __('Self-Host (Single-Tenant)') }}</span>
                </a>
            </div>

            {{-- Live Canvas Preview Card --}}
            <div class="mt-16 max-w-5xl mx-auto rounded-3xl p-1 bg-gradient-to-b from-indigo-500/30 via-slate-800/40 to-slate-900/60 shadow-2xl">
                <div class="bg-slate-950/90 rounded-[22px] p-6 sm:p-8 backdrop-blur-2xl border border-slate-800/80 text-left">
                    <div class="flex items-center justify-between pb-6 border-b border-slate-800/80">
                        <div class="flex items-center gap-3">
                            <span class="h-3 w-3 rounded-full bg-rose-500/80 inline-block"></span>
                            <span class="h-3 w-3 rounded-full bg-amber-500/80 inline-block"></span>
                            <span class="h-3 w-3 rounded-full bg-emerald-500/80 inline-block"></span>
                            <span class="text-xs text-slate-400 font-mono ml-2">{{ __('Tantou Live Canvas • Hellenic Demo Ops') }}</span>
                        </div>
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-emerald-950/60 border border-emerald-800/40 text-emerald-400 text-[11px] font-medium">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            {{ __('WebSockets Connected (Reverb)') }}
                        </div>
                    </div>

                    {{-- Mock 3-Column Board Snapshot --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-6">
                        {{-- Backlog Preview --}}
                        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/60 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">{{ __('Smart Queue Backlog') }}</span>
                                <span class="text-xs bg-slate-800 px-2 py-0.5 rounded-full text-slate-400 font-medium">3</span>
                            </div>
                            <div class="space-y-3">
                                <div class="p-3.5 rounded-xl bg-slate-950/80 border border-indigo-500/30 shadow-sm">
                                    <div class="flex items-center justify-between text-[11px] mb-1.5">
                                        <span class="px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 font-semibold border border-blue-500/20">Software</span>
                                        <span class="px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 font-bold text-[10px]">{{ __('URGENT') }}</span>
                                    </div>
                                    <h4 class="text-xs font-semibold text-white">Stripe billing webhooks</h4>
                                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-900 text-[11px] text-slate-400">
                                        <span>{{ __('Weight') }}: W3</span>
                                        <span class="text-amber-400 font-semibold">★ 38 {{ __('pts') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Active Worker Column Preview (Elena) --}}
                        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/60 p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-6 w-6 rounded-lg bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-bold text-xs">E</div>
                                    <span class="text-xs font-bold text-white">Elena R.</span>
                                </div>
                                <span class="text-[11px] font-semibold text-rose-400">8/10 {{ __('Cap') }}</span>
                            </div>
                            {{-- High load progress bar --}}
                            <div class="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden mb-3">
                                <div class="h-full bg-gradient-to-r from-amber-500 to-rose-500 w-[80%] rounded-full"></div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800">
                                <div class="flex items-center justify-between text-[11px] mb-1.5">
                                    <span class="px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 font-semibold border border-blue-500/20">Software</span>
                                    <span class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 font-medium text-[10px]">{{ __('Co-Op') }} 30%</span>
                                </div>
                                <h4 class="text-xs font-semibold text-white">Resolve MariaDB deadlocks</h4>
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-900 text-[11px] text-slate-400">
                                    <span>{{ __('Helper') }}: Nikos</span>
                                    <span class="text-amber-400 font-semibold">★ 50 {{ __('pts') }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Completed Column Preview --}}
                        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/60 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">{{ __('Completed') }}</span>
                                <span class="text-xs bg-emerald-950/60 border border-emerald-800/40 px-2 py-0.5 rounded-full text-emerald-300 font-medium">2</span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-950/80 border border-emerald-900/30">
                                <div class="flex items-center justify-between text-[11px] mb-1.5">
                                    <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-semibold border border-emerald-500/20">Infrastructure</span>
                                    <span class="text-emerald-400 font-bold">✓ {{ __('DONE') }}</span>
                                </div>
                                <h4 class="text-xs font-semibold text-slate-300">Scaffold TALL stack & Docker</h4>
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-900 text-[11px] text-slate-400">
                                    <span>{{ __('Tantou') }}: Alex</span>
                                    <span class="text-emerald-400 font-semibold">+38 {{ __('pts') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- SECTION 2: 6-CARD FEATURE GRID --}}
        <section id="features" class="max-w-7xl mx-auto px-6 py-20 border-t border-slate-800/80">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-medium mb-3">
                    <span>⚡</span>
                    <span>{{ __('Engineered for Real-Time Human Throughput') }}</span>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-white">
                    {{ __('Everything Modern Technical Operations Teams Need') }}
                </h2>
                <p class="mt-3 text-sm text-slate-400 leading-relaxed">
                    {{ __('Eliminate ticketing queues, middle management bottlenecks, and cognitive burnout with radical capacity transparency.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {{-- Feature 1: Capacity Radar & Smart Queue --}}
                <div class="p-6 rounded-3xl bg-slate-900/60 border border-slate-800/80 hover:border-indigo-500/40 transition-all group">
                    <div class="h-12 w-12 rounded-2xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        📊
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">{{ __('Capacity Radar & Smart Queue (W1–W5)') }}</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Real-time collaborator load bars, automatic FIFO + Priority sorting, and drag-and-drop completion or cancellation (Done vs Canceled with zero-point protection).') }}
                    </p>
                </div>

                {{-- Feature 2: 3-Tier Visibility Firewalls & B2B Isolation --}}
                <div class="p-6 rounded-3xl bg-slate-900/60 border border-slate-800/80 hover:border-purple-500/40 transition-all group">
                    <div class="h-12 w-12 rounded-2xl bg-purple-500/10 text-purple-400 border border-purple-500/20 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        🛡️
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">{{ __('3-Tier Visibility Firewalls & B2B Isolation') }}</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Public, Internal, and Private collaborator columns, plus Strict B2B Client Isolation so external clients only see their own facility\'s tasks while still viewing live technician load.') }}
                    </p>
                </div>

                {{-- Feature 3: Public QR Drop-Box & Live Tracking --}}
                <div class="p-6 rounded-3xl bg-slate-900/60 border border-slate-800/80 hover:border-sky-500/40 transition-all group">
                    <div class="h-12 w-12 rounded-2xl bg-sky-500/10 text-sky-400 border border-sky-500/20 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        📱
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">{{ __('Public QR Drop-Box & Live Tracking (/submit & /track)') }}</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Zero-login request submission per facility/unit with instant #TNT-XXXXXXXX live queue position tracking and requester self-close/resolution.') }}
                    </p>
                </div>

                {{-- Feature 4: Co-op Bounties & Workflow Chains --}}
                <div class="p-6 rounded-3xl bg-slate-900/60 border border-slate-800/80 hover:border-amber-500/40 transition-all group">
                    <div class="h-12 w-12 rounded-2xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        🤝
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">{{ __('Co-op Bounties & Workflow Chains') }}</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Pair up on complex tickets with automatic percentage bounty splits and full handoff audit trails (Nick → Elena → Kostas) to celebrate true team collaboration.') }}
                    </p>
                </div>

                {{-- Feature 5: 2-Way Telegram Bot (GDPR Privacy Mode) --}}
                <div class="p-6 rounded-3xl bg-slate-900/60 border border-slate-800/80 hover:border-blue-500/40 transition-all group">
                    <div class="h-12 w-12 rounded-2xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        ✈️
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">{{ __('2-Way Telegram Bot (GDPR Privacy Mode)') }}</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Instant mobile push alerts with interactive inline buttons ([Claim], [Start], [Complete]), works via Domain Webhook or Intranet IP Long-Polling, with optional GDPR title redaction.') }}
                    </p>
                </div>

                {{-- Feature 6: SLA Analytics & Multi-Timezone Aware --}}
                <div class="p-6 rounded-3xl bg-slate-900/60 border border-slate-800/80 hover:border-emerald-500/40 transition-all group">
                    <div class="h-12 w-12 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        ⏱️
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">{{ __('SLA Analytics & Multi-Timezone Aware') }}</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Track Mean Time to Start (MTTA), Mean Time to Resolution (MTTR), Top Requesting Facilities breakdown, CSV exports, and per-worker local timezone badges.') }}
                    </p>
                </div>
            </div>
        </section>

        {{-- SECTION 3: 4 SUPPORTED DEPLOYMENT ARCHITECTURES --}}
        <section id="architectures" class="max-w-7xl mx-auto px-6 py-20 border-t border-slate-800/80">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-medium mb-3">
                    <span>🏢</span>
                    <span>{{ __('Flexible Topologies') }}</span>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-white">
                    {{ __('Built for High-Stakes Operations Across 4 Architectures') }}
                </h2>
                <p class="mt-3 text-sm text-slate-400 leading-relaxed">
                    {{ __('From hospital wards to multi-tenant IT service providers, Tantou adapts to your exact organizational boundary.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Arch 1 --}}
                <div class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 space-y-3">
                    <div class="text-3xl">🏥</div>
                    <h3 class="text-sm font-bold text-white">{{ __('Internal Enterprise & Hospital IT') }}</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Grouped by clinical unit or department. Visual capacity warnings prevent nurse and technician burnout during critical peak shifts.') }}
                    </p>
                </div>

                {{-- Arch 2 --}}
                <div class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 space-y-3">
                    <div class="text-3xl">💼</div>
                    <h3 class="text-sm font-bold text-white">{{ __('B2B Managed Service Providers (MSPs)') }}</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Strict client isolation across external customer companies. Clients track their tickets without seeing other tenants or internal chatter.') }}
                    </p>
                </div>

                {{-- Arch 3 --}}
                <div class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 space-y-3">
                    <div class="text-3xl">🏷️</div>
                    <h3 class="text-sm font-bold text-white">{{ __('Facility QR Drop-Box') }}</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Frictionless zero-login reporting for hundreds of floor staff across hospitals, campuses, branches, and manufacturing plants with live progress tokens.') }}
                    </p>
                </div>

                {{-- Arch 4 --}}
                <div class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 space-y-3">
                    <div class="text-3xl">🔀</div>
                    <h3 class="text-sm font-bold text-white">{{ __('Multi-Site & Shared Services') }}</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Central technical teams serving multiple physical buildings or regional branches with smart FIFO queue dispatch and workload rebalancing.') }}
                    </p>
                </div>
            </div>
        </section>

        {{-- SECTION 4: TRANSPARENT PRICING --}}
        @if (!\App\Support\Edition::hideMarketing())
            <section id="pricing" class="max-w-7xl mx-auto px-6 py-20 border-t border-slate-800/80">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs font-medium mb-3">
                        <span>💎</span>
                        <span>{{ __('Transparent & Predictable') }}</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-white">
                        {{ __('No Crippled Free Cloud Tiers. Honest Pricing.') }}
                    </h2>
                    <p class="mt-3 text-sm text-slate-400 leading-relaxed">
                        {{ __('No crippled free cloud tiers. Test everything live in our Interactive Demo, self-host the Single-Tenant edition for free on your own hardware, or let us run a fully managed Cloud instance for your team.') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
                    {{-- Tier 1: Self-Hosted Community --}}
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-slate-800/80 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Community') }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-800 text-slate-300">{{ __('Open Source') }}</span>
                            </div>
                            <h3 class="text-xl font-bold text-white mt-2">{{ __('Self-Hosted Community') }}</h3>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-4xl font-extrabold text-white">€0</span>
                                <span class="text-xs text-slate-400 font-medium">/ {{ __('forever') }}</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-2">
                                {{ __('Single-tenant installation on your own server or intranet IP (Docker Compose).') }}
                            </p>

                            <ul class="mt-6 space-y-3 text-xs text-slate-300">
                                <li class="flex items-center gap-2">
                                    <span class="text-emerald-400">✓</span>
                                    <span>{{ __('Single-Tenant Open Source deployment') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-emerald-400">✓</span>
                                    <span>{{ __('Core Capacity Board & Visibility Firewalls') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-emerald-400">✓</span>
                                    <span>{{ __('Public QR Drop-Box & Live Request Tracking') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-emerald-400">✓</span>
                                    <span>{{ __('SLA Analytics, Bottleneck MTTR & CSV Reports') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-emerald-400">✓</span>
                                    <span>{{ __('Bring your own SMTP & Telegram Bot Token (supports Intranet IP Polling)') }}</span>
                                </li>
                            </ul>
                        </div>

                        <div class="mt-8 pt-6 border-t border-slate-800">
                            <a
                                href="#self-host"
                                class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold transition-all"
                            >
                                <span>{{ __('Read Self-Host Guide ↓') }}</span>
                            </a>
                        </div>
                    </div>

                    {{-- Tier 2: Cloud Team (Most Popular) --}}
                    <div class="p-8 rounded-3xl bg-slate-900/90 border-2 border-indigo-500 shadow-2xl shadow-indigo-500/20 flex flex-col justify-between relative">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-indigo-500 text-white text-[10px] font-bold uppercase tracking-wider shadow-sm">
                            {{ __('Most Popular') }}
                        </div>
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">{{ __('Cloud') }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">{{ __('SaaS Managed') }}</span>
                            </div>
                            <h3 class="text-xl font-bold text-white mt-2">{{ __('Cloud Team') }}</h3>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-4xl font-extrabold text-white">€29</span>
                                <span class="text-xs text-slate-400 font-medium">/ {{ __('month') }}</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-2">
                                {{ __('Fully managed Multi-Tenant Cloud workspace with zero infrastructure maintenance.') }}
                            </p>

                            <ul class="mt-6 space-y-3 text-xs text-slate-300">
                                <li class="flex items-center gap-2">
                                    <span class="text-indigo-400 font-bold">✓</span>
                                    <span>{{ __('Dedicated {slug}.tantou.app workspace with automatic SSL') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-indigo-400 font-bold">✓</span>
                                    <span>{{ __('Up to 15 Active Workers & Unlimited Client/Facility accounts') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-indigo-400 font-bold">✓</span>
                                    <span>{{ __('Ready-to-use Platform Telegram Bot (1-click pairing) & Email notifications') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-indigo-400 font-bold">✓</span>
                                    <span>{{ __('Full SLA Analytics, Facility Breakdown & CSV Reports + Daily Managed Backups') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-indigo-400 font-bold">✓</span>
                                    <span>{{ __('Automatic updates & standard technical support') }}</span>
                                </li>
                            </ul>
                        </div>

                        <div class="mt-8 pt-6 border-t border-slate-800">
                            <a
                                href="{{ \App\Support\Edition::canRegisterNewTenant() ? route('register') : route('login') }}"
                                class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all"
                            >
                                <span>{{ __('Start Cloud Team →') }}</span>
                            </a>
                        </div>
                    </div>

                    {{-- Tier 3: Cloud Business / MSP --}}
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-slate-800/80 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-purple-400">{{ __('Enterprise') }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30">{{ __('MSP Ready') }}</span>
                            </div>
                            <h3 class="text-xl font-bold text-white mt-2">{{ __('Cloud Business / MSP') }}</h3>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-4xl font-extrabold text-white">€79</span>
                                <span class="text-xs text-slate-400 font-medium">/ {{ __('month') }}</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-2">
                                {{ __('Engineered for MSPs, hospital networks, and multi-department enterprise teams.') }}
                            </p>

                            <ul class="mt-6 space-y-3 text-xs text-slate-300">
                                <li class="flex items-center gap-2">
                                    <span class="text-purple-400 font-bold">✓</span>
                                    <span>{{ __('Unlimited Active Workers & Unlimited Departments') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-purple-400 font-bold">✓</span>
                                    <span>{{ __('Custom Domain (ops.yourcompany.com) & White-label Client Drop-Box') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-purple-400 font-bold">✓</span>
                                    <span>{{ __('Strict B2B Client Isolation & Custom Dedicated Telegram Bot with GDPR Privacy Mode') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-purple-400 font-bold">✓</span>
                                    <span>{{ __('Priority Support, Extended Attachment Storage & 99.9% Uptime SLA') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-purple-400 font-bold">✓</span>
                                    <span>{{ __('Dedicated onboarding assistance & custom integrations') }}</span>
                                </li>
                            </ul>
                        </div>

                        <div class="mt-8 pt-6 border-t border-slate-800">
                            <a
                                href="{{ \App\Support\Edition::canRegisterNewTenant() ? route('register') : route('login') }}"
                                class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-lg shadow-purple-600/30 transition-all"
                            >
                                <span>{{ __('Start Cloud Business →') }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- SECTION 5: SELF-HOSTED SINGLE-TENANT INSTALLATION GUIDE --}}
        <section id="self-host" class="max-w-7xl mx-auto px-6 py-20 border-t border-slate-800/80">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-medium mb-3">
                    <span>🐳</span>
                    <span>{{ __('100% Free & Open-Core') }}</span>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-white">
                    {{ __('Self-Host Tantou on Your Own Server in 60 Seconds') }}
                </h2>
                <p class="mt-3 text-sm text-slate-400 leading-relaxed">
                    {{ __('Deploy a private, single-tenant instance on your local network, private VPS, or air-gapped intranet server with Docker Compose.') }}
                </p>
            </div>

            <div class="max-w-3xl mx-auto space-y-6">
                {{-- Interactive Terminal Card --}}
                <div
                    x-data="{ copied: false, commands: 'git clone https://github.com/ktsourdinis/tantou-oss.git && cd tantou-oss\ncp .env.example .env\ndocker compose up -d\ndocker exec -it tantou_app php artisan migrate --seed' }"
                    class="rounded-3xl bg-slate-950 border border-slate-800 overflow-hidden shadow-2xl"
                >
                    <div class="px-6 py-4 bg-slate-900/80 border-b border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="w-3 h-3 rounded-full bg-rose-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-amber-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-emerald-500/80"></span>
                            <span class="text-xs font-mono text-slate-400 ml-2">bash • 4-step quickstart</span>
                        </div>

                        <button
                            type="button"
                            @click="copyToClipboard(commands); copied = true; setTimeout(() => copied = false, 2500)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-300 hover:text-white transition-all cursor-pointer"
                        >
                            <span x-show="!copied">📋 {{ __('Copy Commands') }}</span>
                            <span x-show="copied" class="text-emerald-400 font-semibold">✓ {{ __('Copied!') }}</span>
                        </button>
                    </div>

                    <div class="p-6 font-mono text-xs text-slate-300 leading-loose overflow-x-auto space-y-2">
                        <div class="text-slate-500 select-none"># 1. Clone repository & enter workspace</div>
                        <div class="text-emerald-400">git clone https://github.com/ktsourdinis/tantou-oss.git && cd tantou-oss</div>

                        <div class="text-slate-500 select-none pt-2"># 2. Configure environment (Community Single-Tenant default)</div>
                        <div class="text-emerald-400">cp .env.example .env</div>

                        <div class="text-slate-500 select-none pt-2"># 3. Boot containers (PHP 8.4, MariaDB 11, Redis, Reverb WebSockets)</div>
                        <div class="text-emerald-400">docker compose up -d</div>

                        <div class="text-slate-500 select-none pt-2"># 4. Run migrations & demo seeders</div>
                        <div class="text-emerald-400">docker exec -it tantou_app php artisan migrate --seed</div>
                    </div>
                </div>

                {{-- Intranet IP Mode Callout --}}
                <div class="p-5 rounded-2xl bg-indigo-950/20 border border-indigo-500/20 text-xs text-slate-300 space-y-2">
                    <div class="font-bold text-indigo-300 flex items-center gap-2">
                        <span>💡</span>
                        <span>{{ __('Intranet IP Mode vs Domain Mode:') }}</span>
                    </div>
                    <p class="leading-relaxed text-slate-400">
                        {{ __('Tantou operates seamlessly on raw IP addresses (e.g. http://192.168.1.100) without requiring DNS subdomains or SSL. For firewalled intranets, run php artisan tantou:telegram-poll for two-way Telegram actions without opening any inbound ports or webhooks.') }}
                    </p>
                </div>

                <div class="text-center text-xs text-slate-500">
                    {{ __('System Requirements: Docker & Docker Compose • Minimum 1 CPU / 1GB RAM • Works on Linux, macOS, or Windows (WSL2).') }}
                </div>
            </div>
        </section>

        {{-- Footer --}}
        <footer class="border-t border-slate-800/80 bg-slate-950 py-10 px-6 text-center text-xs text-slate-500">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-300">Tantou (担当)</span>
                    <span>•</span>
                    <span>{{ __('The Open-Core Human Capacity OS for Real-Time Operations') }}</span>
                </div>
                <div class="flex items-center gap-4 text-slate-400 font-mono text-[11px]">
                    <a href="https://github.com/ktsourdinis/tantou-oss" target="_blank" rel="noopener noreferrer" class="hover:text-white transition-colors">
                        GitHub (Open-Core)
                    </a>
                    <span>•</span>
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

        <script>
            window.copyToClipboard = window.copyToClipboard || function (text) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                }
                return new Promise(function (resolve, reject) {
                    try {
                        var textArea = document.createElement('textarea');
                        textArea.value = text;
                        textArea.style.position = 'fixed';
                        textArea.style.left = '-999999px';
                        textArea.style.top = '-999999px';
                        textArea.setAttribute('readonly', '');
                        document.body.appendChild(textArea);
                        textArea.focus();
                        textArea.select();
                        var successful = document.execCommand('copy');
                        document.body.removeChild(textArea);
                        if (successful) {
                            resolve();
                        } else {
                            reject(new Error('Copy command failed'));
                        }
                    } catch (err) {
                        reject(err);
                    }
                });
            };
        </script>
    </body>
</html>
