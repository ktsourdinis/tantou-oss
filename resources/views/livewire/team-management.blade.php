<div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans selection:bg-indigo-500 selection:text-white">
    {{-- Top Navigation Bar --}}
    <header class="border-b border-slate-800/80 bg-slate-900/70 backdrop-blur-xl sticky top-0 z-30 px-4 sm:px-6 py-3">
        <div class="max-w-[1920px] mx-auto flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            {{-- Left Section: Brand & Primary Navigation Switcher --}}
            <div class="flex items-center justify-between md:justify-start gap-4 w-full md:w-auto">
                <div class="flex items-center space-x-3 min-w-0">
                    <a href="{{ route('landing') }}" class="h-9 w-9 sm:h-10 sm:w-10 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-amber-400 flex items-center justify-center font-bold text-base sm:text-lg text-white shadow-lg shadow-indigo-500/20 hover:scale-105 transition-transform shrink-0">
                        担
                    </a>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h1 class="text-lg sm:text-xl font-bold tracking-tight text-white truncate">Tantou <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">担当</span></h1>
                            <span class="text-slate-600 text-sm">/</span>
                            <span class="text-slate-300 font-semibold text-xs sm:text-sm truncate">{{ $tenantName }}</span>
                            @if(\App\Support\Edition::isCloud())
                                <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-medium bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    <span>{{ $tenantSlug }}.{{ \App\Support\Edition::baseDomain() }}</span>
                                </span>
                            @else
                                <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-medium bg-slate-800/80 text-slate-400 border border-slate-700/60">
                                    <span>/{{ $tenantSlug }}</span>
                                </span>
                            @endif

                            @if (auth()->user()?->isAdmin())
                                <button
                                    wire:click="openWorkspaceSettingsModal"
                                    type="button"
                                    class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 border border-transparent hover:border-slate-700 transition-all cursor-pointer text-xs"
                                    title="{{ __('Workspace Settings') }}"
                                >
                                    ⚙️
                                </button>
                            @endif
                        </div>
                        <p class="hidden md:block text-[11px] text-slate-400 truncate">{{ __('Human Capacity OS & Workload Marketplace') }}</p>
                    </div>
                </div>

                {{-- Primary Navigation Pill Switcher (Capacity Board vs Team & Leaderboard) --}}
                <nav class="flex items-center p-1 rounded-xl bg-slate-950/80 border border-slate-800 text-xs shrink-0">
                    <a
                        href="{{ route('board') }}"
                        class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg font-medium text-slate-400 hover:text-slate-200 transition-all"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" /></svg>
                        <span>{{ __('Capacity Board') }}</span>
                    </a>

                    @if (auth()->user()?->role !== 'client')
                        <a
                            href="{{ route('team') }}"
                            class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg font-semibold bg-indigo-600 text-white shadow-sm transition-all"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            <span class="hidden sm:inline">{{ __('Team & Leaderboard') }}</span>
                        </a>
                    @endif

                    @if (auth()->user()?->isSuperAdmin())
                        <a
                            href="{{ route('superadmin.dashboard') }}"
                            class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30 hover:bg-amber-500/25 transition-all"
                            title="{{ __('Superadmin Control Plane') }}"
                        >
                            <span>🛡️</span>
                            <span class="hidden sm:inline">{{ __('Superadmin') }}</span>
                        </a>
                    @endif
                </nav>
            </div>

            {{-- Right Section: Language Switcher, Controls, User Profile & Actions --}}
            <div class="flex items-center gap-2.5 flex-wrap w-full md:w-auto justify-between sm:justify-end">
                {{-- Language Switcher (EN | ΕΛ) --}}
                <x-language-switcher />

                {{-- Live Reverb Status Indicator --}}
                <div class="hidden sm:inline-flex items-center gap-2 px-2.5 py-1.5 rounded-full bg-emerald-950/60 border border-emerald-800/40 text-emerald-400 text-xs font-medium">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="hidden md:inline">{{ __('Reverb Sync Live') }}</span>
                </div>

                @auth
                    {{-- Telegram Pairing & Notifications Button --}}
                    <button
                        wire:click="openTelegramModal"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-800/60 hover:bg-slate-800 border border-slate-700/60 text-xs font-medium text-slate-300 hover:text-white transition-colors cursor-pointer"
                        title="{{ __('Telegram Bot & Notifications') }}"
                    >
                        <span>✈️</span>
                        <span class="hidden sm:inline">{{ __('Telegram') }}</span>
                        @if (filled(auth()->user()->telegram_chat_id))
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        @endif
                    </button>

                    {{-- User Profile Indicator --}}
                    <div class="flex items-center gap-2 px-2.5 py-1 rounded-xl bg-slate-800/70 border border-slate-700/60 text-xs max-w-[140px] sm:max-w-none">
                        <div class="h-6 w-6 rounded-lg bg-indigo-500/20 text-indigo-300 font-bold flex items-center justify-center text-xs shrink-0">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="font-semibold text-slate-200 leading-tight truncate text-xs">{{ auth()->user()->name }}</span>
                            <div class="hidden sm:flex items-center gap-1.5 text-[10px]">
                                <span class="capitalize px-1.5 py-0.2 rounded font-medium {{ auth()->user()->role === 'admin' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : (auth()->user()->role === 'worker' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-blue-500/20 text-blue-300 border border-blue-500/30') }}">
                                    {{ __(ucfirst(auth()->user()->role)) }}
                                </span>
                                @if (auth()->user()->role === 'worker')
                                    <span class="text-amber-400 font-medium">★ {{ auth()->user()->total_points }} pts</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Logout Button --}}
                    <button
                        wire:click="logout"
                        type="button"
                        class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition-all cursor-pointer"
                        title="{{ __('Sign Out') }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                @endauth
            </div>
        </div>
    </header>

    {{-- Main Sub-Header & Section Tabs --}}
    <div class="border-b border-slate-800/60 bg-slate-900/40 px-6 py-4">
        <div class="max-w-[1920px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span>👥</span>
                    <span>{{ __('Workspace Team & Capacity Management') }}</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    {{ __('Live workforce capacity monitoring, department structuring, and gamified Co-op performance.') }}
                </p>
            </div>

            {{-- Tab Switcher --}}
            <div class="flex items-center gap-2 p-1 rounded-xl bg-slate-950/80 border border-slate-800 text-xs">
                <button
                    wire:click="setActiveTab('leaderboard')"
                    type="button"
                    class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg font-semibold transition-all cursor-pointer {{ $activeTab === 'leaderboard' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                >
                    <span>🏆</span>
                    <span>{{ __('Leaderboard') }}</span>
                </button>

                <button
                    wire:click="setActiveTab('departments')"
                    type="button"
                    class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg font-semibold transition-all cursor-pointer {{ $activeTab === 'departments' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                >
                    <span>🏢</span>
                    <span>{{ __('Departments') }}</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'departments' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400' }}">
                        {{ $departments->count() }}
                    </span>
                </button>

                <button
                    wire:click="setActiveTab('roster')"
                    type="button"
                    class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg font-semibold transition-all cursor-pointer {{ $activeTab === 'roster' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                >
                    <span>👥</span>
                    <span>{{ __('Team Roster') }}</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'roster' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400' }}">
                        {{ $rosterUsers->count() }}
                    </span>
                </button>

                @if (in_array(auth()->user()?->role, ['admin', 'coordinator'], true))
                    <button
                        wire:click="setActiveTab('analytics')"
                        type="button"
                        class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg font-semibold transition-all cursor-pointer {{ $activeTab === 'analytics' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                    >
                        <span>📊</span>
                        <span>{{ __('Analytics & SLA') }}</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Content Area --}}
    <main class="flex-1 max-w-[1920px] w-full mx-auto p-6">
        {{-- TAB 1: GAMIFICATION & CO-OP LEADERBOARD --}}
        @if ($activeTab === 'leaderboard')
            <div class="space-y-6">
                {{-- Leaderboard Overview Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-5 rounded-2xl bg-gradient-to-br from-amber-500/10 via-slate-900/90 to-slate-900 border border-amber-500/20 shadow-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">{{ __('Total Bounty Awarded') }}</span>
                            <span class="text-xl">⚡</span>
                        </div>
                        <div class="mt-2 text-2xl md:text-3xl font-extrabold text-white">
                            {{ number_format($leaderboardWorkers->sum('total_points')) }} <span class="text-xs font-semibold text-amber-400">pts</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">{{ __('Total capacity points earned by team members') }}</p>
                    </div>

                    <div class="p-5 rounded-2xl bg-gradient-to-br from-indigo-500/10 via-slate-900/90 to-slate-900 border border-indigo-500/20 shadow-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">{{ __('TASKS DELIVERED') }}</span>
                            <span class="text-xl">🎯</span>
                        </div>
                        <div class="mt-2 text-2xl md:text-3xl font-extrabold text-white">
                            {{ $leaderboardWorkers->sum('completed_tantou_count') }} <span class="text-xs font-semibold text-indigo-400">tasks</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">{{ __('Requests fully completed as primary assignee') }}</p>
                    </div>

                    <div class="p-5 rounded-2xl bg-gradient-to-br from-purple-500/10 via-slate-900/90 to-slate-900 border border-purple-500/20 shadow-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-purple-400 uppercase tracking-wider">{{ __('Co-op Collaborations') }}</span>
                            <span class="text-xl">🤝</span>
                        </div>
                        <div class="mt-2 text-2xl md:text-3xl font-extrabold text-white">
                            {{ $leaderboardWorkers->sum('completed_coop_count') }} <span class="text-xs font-semibold text-purple-400">assists</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">{{ __('Cross-worker helper splits and peer-assists completed') }}</p>
                    </div>
                </div>

                {{-- Leaderboard Standings Table --}}
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 backdrop-blur-xl shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-800/80 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span>🏆</span>
                            <span>{{ __('Worker Capacity & Bounty Rankings') }}</span>
                        </h3>
                        <span class="text-xs text-slate-400">{{ __('Updated dynamically with task completions') }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-950/60 text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-800">
                                <tr>
                                    <th class="py-3 px-4 text-center w-16">{{ __('Rank') }}</th>
                                    <th class="py-3 px-4">{{ __('Worker') }}</th>
                                    <th class="py-3 px-4">{{ __('Department') }}</th>
                                    <th class="py-3 px-4 text-right">{{ __('Bounty Score') }}</th>
                                    <th class="py-3 px-4 text-center">{{ __('Current Capacity') }}</th>
                                    <th class="py-3 px-4 text-center">{{ __('SOLO TASKS') }}</th>
                                    <th class="py-3 px-4 text-center">{{ __('Co-op Assists') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                @forelse ($leaderboardWorkers as $index => $worker)
                                    @php
                                        $rank = $index + 1;
                                        $ratio = $worker->max_capacity > 0 ? min(100, round(($worker->current_capacity / $worker->max_capacity) * 100)) : 0;
                                        $barColor = $ratio >= 100 ? 'bg-rose-500' : ($ratio >= 70 ? 'bg-amber-500' : 'bg-emerald-500');
                                    @endphp
                                    <tr wire:key="leaderboard-worker-{{ $worker->id }}-{{ $worker->updated_at?->timestamp }}" class="hover:bg-slate-800/40 transition-colors {{ $rank === 1 ? 'bg-amber-500/5' : '' }}">
                                        {{-- Rank Column --}}
                                        <td class="py-3.5 px-4 text-center font-bold text-sm">
                                            @if ($rank === 1)
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/40 font-extrabold shadow-sm">
                                                    🥇 1
                                                </span>
                                            @elseif ($rank === 2)
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-slate-400/20 text-slate-200 border border-slate-400/40 font-extrabold shadow-sm">
                                                    🥈 2
                                                </span>
                                            @elseif ($rank === 3)
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-amber-700/20 text-amber-500 border border-amber-700/40 font-extrabold shadow-sm">
                                                    🥉 3
                                                </span>
                                            @else
                                                <span class="text-slate-500 font-semibold text-xs">
                                                    #{{ $rank }}
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Worker Info --}}
                                        <td class="py-3.5 px-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-xl bg-indigo-600/30 border border-indigo-500/40 text-indigo-300 font-bold flex items-center justify-center text-xs">
                                                    {{ strtoupper(substr($worker->name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <span class="font-bold text-slate-100 block">{{ $worker->name }}</span>
                                                    <span class="text-[10px] text-slate-400">{{ $worker->email }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Department --}}
                                        <td class="py-3.5 px-4">
                                            @if ($worker->department)
                                                <span
                                                    class="px-2 py-0.5 rounded text-[10px] font-semibold border truncate inline-block max-w-[120px]"
                                                    style="background-color: {{ $worker->department->color }}20; color: {{ $worker->department->color }}; border-color: {{ $worker->department->color }}40;"
                                                >
                                                    {{ $worker->department->name }}
                                                </span>
                                            @else
                                                <span class="text-slate-500 italic text-[11px]">{{ __('General Team') }}</span>
                                            @endif
                                        </td>

                                        {{-- Total Points --}}
                                        <td class="py-3.5 px-4 text-right">
                                            <span class="inline-flex items-center gap-1 font-mono font-bold text-sm text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-xl border border-amber-500/30">
                                                <span>⚡</span>
                                                <span>{{ number_format($worker->total_points) }} pts</span>
                                            </span>
                                        </td>

                                        {{-- Capacity Meter --}}
                                        <td class="py-3.5 px-4">
                                            <div class="max-w-[180px] mx-auto">
                                                <div class="flex items-center justify-between text-[10px] mb-1">
                                                    <span class="text-slate-400 font-medium">{{ $worker->current_capacity }} / {{ $worker->max_capacity }} {{ __('load') }}</span>
                                                    <span class="font-bold {{ $ratio >= 100 ? 'text-rose-400' : ($ratio >= 70 ? 'text-amber-400' : 'text-emerald-400') }}">{{ $ratio }}%</span>
                                                </div>
                                                <div class="w-full bg-slate-950 rounded-full h-2 overflow-hidden border border-slate-800">
                                                    <div class="{{ $barColor }} h-2 rounded-full transition-all duration-300" style="width: {{ $ratio }}%"></div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Tasks Completed --}}
                                        <td class="py-3.5 px-4 text-center">
                                            <span class="inline-flex items-center gap-1 font-semibold text-xs text-indigo-300 bg-indigo-500/10 px-2 py-0.5 rounded-lg border border-indigo-500/20">
                                                <span>🎯</span>
                                                <span>{{ $worker->completed_tantou_count }}</span>
                                            </span>
                                        </td>

                                        {{-- Co-op Assists --}}
                                        <td class="py-3.5 px-4 text-center">
                                            <span class="inline-flex items-center gap-1 font-semibold text-xs text-purple-300 bg-purple-500/10 px-2 py-0.5 rounded-lg border border-purple-500/20">
                                                <span>🤝</span>
                                                <span>{{ $worker->completed_coop_count }}</span>
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-12 text-center text-slate-500 italic">
                                            {{ __('No workers found in this organization.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- TAB 2: DEPARTMENTS MANAGEMENT --}}
        @if ($activeTab === 'departments')
            <div class="space-y-6">
                {{-- Section Header & Action --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>🏢</span>
                            <span>{{ __('Organization Departments') }}</span>
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{ __('Categorize team workers and aggregate capacity loads across business functions.') }}
                        </p>
                    </div>

                    @if (auth()->user()?->role === 'admin')
                        <button
                            wire:click="openCreateDepartmentModal"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            <span>{{ __('New Department') }}</span>
                        </button>
                    @endif
                </div>

                {{-- Departments Cards Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse ($departments as $dept)
                        @php
                            $statusBadge = match($dept->status) {
                                'danger' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                'warning' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                default => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                            };
                            $progressBar = match($dept->status) {
                                'danger' => 'bg-rose-500',
                                'warning' => 'bg-amber-500',
                                default => 'bg-emerald-500',
                            };
                        @endphp
                        <div wire:key="department-card-{{ $dept->id }}-{{ $dept->updated_at?->timestamp }}" class="p-5 rounded-2xl border border-slate-800 bg-slate-900/80 backdrop-blur-xl shadow-lg border-l-4 transition-all hover:border-slate-700" style="border-left-color: {{ $dept->color }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-4 h-4 rounded-full shadow-sm" style="background-color: {{ $dept->color }}"></div>
                                    <div>
                                        <h4 class="font-bold text-white text-sm leading-snug">{{ $dept->name }}</h4>
                                        <span class="text-[10px] font-mono text-slate-500">/{{ $dept->slug }}</span>
                                    </div>
                                </div>

                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $statusBadge }}">
                                    {{ $dept->status === 'danger' ? __('Overloaded') : ($dept->status === 'warning' ? __('High Load') : __('Healthy')) }}
                                </span>
                            </div>

                            {{-- Aggregate Capacity Progress --}}
                            <div class="mt-4 pt-3 border-t border-slate-800">
                                <div class="flex items-center justify-between text-xs mb-1.5">
                                    <span class="text-slate-400 font-medium">{{ __('Workers:') }} <strong class="text-white">{{ $dept->worker_count }}</strong></span>
                                    <span class="text-slate-300 font-semibold">{{ $dept->current_capacity }} / {{ $dept->max_capacity }} {{ __('load') }} ({{ $dept->ratio }}%)</span>
                                </div>

                                <div class="w-full bg-slate-950 rounded-full h-2 overflow-hidden border border-slate-800">
                                    <div class="{{ $progressBar }} h-2 rounded-full transition-all duration-300" style="width: {{ min(100, $dept->ratio) }}%"></div>
                                </div>
                            </div>

                            {{-- Telegram Group Integration (Admin Only) --}}
                            @if (auth()->user()?->role === 'admin')
                                <div class="mt-3 pt-2.5 border-t border-slate-800/80 flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs">✈️</span>
                                        <span class="text-slate-400 text-[11px] font-medium">{{ __('Telegram Group:') }}</span>
                                        @if (filled($dept->telegram_chat_id))
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                                {{ __('Linked') }}
                                            </span>
                                        @else
                                            <span class="text-[10px] text-slate-500 italic">{{ __('Not Linked') }}</span>
                                        @endif
                                    </div>

                                    @if (filled($dept->telegram_chat_id))
                                        <button
                                            wire:click="unlinkDepartmentTelegram({{ $dept->id }})"
                                            wire:confirm="{{ __('Unlink Telegram group from this department?') }}"
                                            type="button"
                                            class="text-[10px] text-rose-400 hover:text-rose-300 font-medium cursor-pointer"
                                        >
                                            {{ __('Unlink') }}
                                        </button>
                                    @else
                                        <button
                                            wire:click="generateDepartmentTelegramToken({{ $dept->id }})"
                                            type="button"
                                            class="text-[10px] text-sky-400 hover:text-sky-300 font-medium cursor-pointer"
                                        >
                                            {{ __('Get Link Command') }}
                                        </button>
                                    @endif
                                </div>

                                @if ($selectedDepartmentTelegramId === $dept->id && $departmentTelegramToken)
                                    <div
                                        x-data="{ copied: false }"
                                        class="mt-2 p-2 rounded-xl bg-slate-950 border border-sky-500/30 space-y-1.5 text-xs animate-fadeIn"
                                    >
                                        <div class="flex items-center justify-between text-[10px] text-sky-400 font-semibold">
                                            <span>{{ __('Add bot to Group & send:') }}</span>
                                            <button
                                                type="button"
                                                wire:click="generateDepartmentTelegramToken({{ $dept->id }})"
                                                class="text-slate-400 hover:text-white"
                                            >
                                                🔄
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <input
                                                type="text"
                                                readonly
                                                value="/link dept_{{ $departmentTelegramToken }}"
                                                class="w-full px-2 py-1 text-[11px] font-mono bg-slate-900 border border-slate-800 rounded-lg text-white select-all focus:outline-none"
                                            >
                                            <button
                                                type="button"
                                                @click="copyToClipboard('/link dept_{{ $departmentTelegramToken }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="shrink-0 px-2.5 py-1 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-[10px] font-semibold transition-all cursor-pointer"
                                            >
                                                <span x-show="!copied">{{ __('Copy') }}</span>
                                                <span x-show="copied" class="text-white">✓</span>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            @endif

                            {{-- Department Actions (QR Drop-Box + Admin Edit/Delete) --}}
                            <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between gap-2 flex-wrap">
                                <button
                                    wire:click="openDepartmentQrModal({{ $dept->id }})"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-semibold transition-colors cursor-pointer"
                                >
                                    <span>📱</span>
                                    <span>{{ __('QR Drop-Box') }}</span>
                                </button>

                                @if (auth()->user()?->role === 'admin')
                                    <div class="flex items-center gap-2">
                                        <button
                                            wire:click="openEditDepartmentModal({{ $dept->id }})"
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors cursor-pointer"
                                        >
                                            {{ __('Edit') }}
                                        </button>
                                        <button
                                            wire:click="deleteDepartment({{ $dept->id }})"
                                            wire:confirm="{{ __('Are you sure you want to delete department :name? Workers will be unassigned.', ['name' => $dept->name]) }}"
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-xs font-semibold transition-colors cursor-pointer"
                                        >
                                            {{ __('Delete') }}
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-12 text-center text-slate-500 italic rounded-2xl border border-slate-800/60 bg-slate-900/40">
                            {{ __('No departments defined yet. Click "New Department" to organize team capacity.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- TAB 3: TEAM & CLIENT ROSTER --}}
        @if ($activeTab === 'roster')
            <div class="space-y-6">
                {{-- Filter Toolbar --}}
                <div class="p-4 rounded-2xl border border-slate-800 bg-slate-900/80 backdrop-blur-xl shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-3 flex-wrap">
                        {{-- Search Input --}}
                        <div class="relative w-64">
                            <input
                                wire:model.live.debounce.250ms="rosterSearch"
                                type="text"
                                placeholder="{{ __('Search by name or email...') }}"
                                class="w-full rounded-xl bg-slate-950 border border-slate-800 pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                            >
                            <svg class="w-4 h-4 text-slate-500 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>

                        {{-- Role Filter Pills --}}
                        <div class="flex items-center p-1 rounded-xl bg-slate-950 border border-slate-800 text-xs">
                            @foreach (['all' => __('All Roles'), 'coordinator' => __('Coordinators'), 'worker' => __('Workers'), 'client' => __('Clients'), 'admin' => __('Admins')] as $rVal => $rLabel)
                                <button
                                    wire:click="$set('rosterRoleFilter', '{{ $rVal }}')"
                                    type="button"
                                    class="px-2.5 py-1 rounded-lg font-medium transition-all cursor-pointer {{ $rosterRoleFilter === $rVal ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                                >
                                    {{ $rLabel }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Department Filter Dropdown --}}
                        <select
                            wire:model.live="rosterDepartmentFilter"
                            class="rounded-xl bg-slate-950 border border-slate-800 px-3 py-1.5 text-xs text-slate-300 focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">{{ __('All Departments') }}</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Admin Add Member Button --}}
                    @if (auth()->user()?->role === 'admin')
                        <button
                            wire:click="openCreateUserModal"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer shrink-0"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            <span>{{ __('Add Member') }}</span>
                        </button>
                    @endif
                </div>

                {{-- Roster Table --}}
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 backdrop-blur-xl shadow-xl overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-950/60 text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-800">
                            <tr>
                                <th class="py-3 px-4">{{ __('Member') }}</th>
                                <th class="py-3 px-4">{{ __('Role') }}</th>
                                <th class="py-3 px-4">{{ __('Department') }}</th>
                                <th class="py-3 px-4">{{ __('Visibility') }}</th>
                                <th class="py-3 px-4">{{ __('Status') }}</th>
                                <th class="py-3 px-4 text-center">{{ __('Capacity Load') }}</th>
                                <th class="py-3 px-4 text-right">{{ __('Points') }}</th>
                                @if (auth()->user()?->role === 'admin')
                                    <th class="py-3 px-4 text-right">{{ __('Actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @forelse ($rosterUsers as $user)
                                @php
                                    $roleBadge = match($user->role) {
                                        'admin' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                        'coordinator' => 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
                                        'worker' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                        default => 'bg-blue-500/20 text-blue-300 border-blue-500/30',
                                    };
                                    $isWorkerOrCoord = in_array($user->role, ['worker', 'coordinator'], true);
                                @endphp
                                <tr wire:key="roster-member-{{ $user->id }}-{{ $user->updated_at?->timestamp }}" class="hover:bg-slate-800/40 transition-colors">
                                    {{-- Member Name & Email --}}
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 font-bold flex items-center justify-center text-xs">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="font-bold text-slate-100">{{ $user->name }}</span>
                                                    @if ($user->facility_name)
                                                        <span class="px-1.5 py-0.5 rounded bg-sky-500/10 text-sky-400 border border-sky-500/20 text-[10px] font-medium" title="{{ __('Facility / Unit') }}">
                                                            🏢 {{ $user->facility_name }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <span class="text-[10px] text-slate-400 block">{{ $user->email }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Role Badge --}}
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $roleBadge }}">
                                            {{ $user->role }}
                                        </span>
                                    </td>

                                    {{-- Department --}}
                                    <td class="py-3.5 px-4">
                                        @if ($user->department)
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-semibold border truncate inline-block max-w-[120px]"
                                                style="background-color: {{ $user->department->color }}20; color: {{ $user->department->color }}; border-color: {{ $user->department->color }}40;"
                                            >
                                                {{ $user->department->name }}
                                            </span>
                                        @else
                                            <span class="text-slate-500 italic text-[11px]">—</span>
                                        @endif
                                    </td>

                                    {{-- Visibility Mode --}}
                                    <td class="py-3.5 px-4">
                                        @if (auth()->user()?->canChangeBoardVisibilityOf($user))
                                            <select
                                                wire:change="quickUpdateVisibility({{ $user->id }}, $event.target.value)"
                                                class="text-[10px] bg-slate-950 border border-slate-700 rounded-lg px-2 py-0.5 text-slate-300 cursor-pointer focus:outline-none"
                                            >
                                                <option value="public" @selected(($user->visibility ?? $user->board_visibility) === 'public')>{{ __('Public') }}</option>
                                                <option value="internal" @selected(($user->visibility ?? $user->board_visibility) === 'internal')>{{ __('Internal') }}</option>
                                                <option value="private" @selected(($user->visibility ?? $user->board_visibility) === 'private')>{{ __('Private') }}</option>
                                                <option value="none" @selected(($user->visibility ?? $user->board_visibility) === 'none')>{{ __('None (Hide)') }}</option>
                                            </select>
                                        @elseif ($user->role === 'client')
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider cursor-default bg-slate-800/80 text-slate-400 border border-slate-700/80" title="{{ __('Locked to None (Hidden) — Clients do not have a capacity board column.') }}">
                                                🔒 {{ __('None (Hidden)') }}
                                            </span>
                                        @else
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider cursor-default {{ ($user->visibility ?? $user->board_visibility) === 'public' ? 'bg-indigo-500/15 text-indigo-300 border border-indigo-500/30' : (($user->visibility ?? $user->board_visibility) === 'internal' ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30' : 'bg-purple-500/15 text-purple-300 border border-purple-500/30') }}">
                                                {{ $user->visibility ?? $user->board_visibility }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Availability Working / Away --}}
                                    <td class="py-3.5 px-4">
                                        @if (auth()->user()?->role === 'admin')
                                            <button
                                                wire:click="quickToggleAvailability({{ $user->id }})"
                                                type="button"
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg text-[10px] font-semibold border transition-all cursor-pointer {{ $user->is_available ? 'bg-emerald-950/60 text-emerald-300 border-emerald-800/50 hover:bg-emerald-900/60' : 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700' }}"
                                                title="{{ __('Click to toggle Working / Away') }}"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full {{ $user->is_available ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                                                <span>{{ $user->is_available ? __('Working') : __('Away') }}</span>
                                            </button>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-[10px] font-medium {{ $user->is_available ? 'text-emerald-400' : 'text-slate-400' }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $user->is_available ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                                                <span>{{ $user->is_available ? __('Working') : __('Away') }}</span>
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Capacity & Quick Adjust --}}
                                    <td class="py-3.5 px-4 text-center">
                                        @if ($isWorkerOrCoord)
                                            <div class="flex items-center justify-center gap-2">
                                                <span class="font-mono text-xs text-slate-200">
                                                    <strong class="text-indigo-400">{{ $user->current_capacity }}</strong> / {{ $user->max_capacity }}
                                                </span>

                                                @if (auth()->user()?->role === 'admin')
                                                    {{-- Quick Adjust Capacity (+/-) --}}
                                                    <div class="inline-flex items-center rounded-lg bg-slate-950 border border-slate-800 p-0.5">
                                                        <button
                                                            wire:click="quickAdjustCapacity({{ $user->id }}, -1)"
                                                            type="button"
                                                            class="w-5 h-5 rounded hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center text-xs font-bold transition-colors cursor-pointer"
                                                            title="{{ __('Decrease Max Capacity') }}"
                                                        >
                                                            -
                                                        </button>
                                                        <button
                                                            wire:click="quickAdjustCapacity({{ $user->id }}, 1)"
                                                            type="button"
                                                            class="w-5 h-5 rounded hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center text-xs font-bold transition-colors cursor-pointer"
                                                            title="{{ __('Increase Max Capacity') }}"
                                                        >
                                                            +
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-slate-500 text-[11px]">—</span>
                                        @endif
                                    </td>

                                    {{-- Points --}}
                                    <td class="py-3.5 px-4 text-right">
                                        @if ($isWorkerOrCoord)
                                            <span class="font-mono font-bold text-amber-400 text-xs">
                                                ⚡ {{ $user->total_points }}
                                            </span>
                                        @else
                                            <span class="text-slate-500 text-[11px]">—</span>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    @if (auth()->user()?->role === 'admin')
                                        <td class="py-3.5 px-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    wire:click="openEditUserModal({{ $user->id }})"
                                                    type="button"
                                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors cursor-pointer"
                                                >
                                                    {{ __('Edit') }}
                                                </button>

                                                @if ($user->id !== auth()->id())
                                                    <button
                                                        wire:click="deleteUser({{ $user->id }})"
                                                        wire:confirm="{{ __('Are you sure you want to delete member :name?', ['name' => $user->name]) }}"
                                                        type="button"
                                                        class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-xs font-semibold transition-colors cursor-pointer"
                                                    >
                                                        {{ __('Delete') }}
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr wire:key="roster-empty-row">
                                    <td colspan="8" class="py-12 text-center text-slate-500 italic">
                                        {{ __('No members found matching the selected filters.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- TAB 4: ANALYTICS & SLA PERFORMANCE --}}
        @if ($activeTab === 'analytics' && ! empty($analytics))
            <div class="space-y-6">
                {{-- Analytics Header: Period Filter & CSV Export --}}
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 rounded-2xl bg-slate-900/80 border border-slate-800 backdrop-blur-xl shadow-xl">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>📊</span>
                            <span>{{ __('Analytics & SLA Performance') }}</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">
                            {{ __('Mean response times, resolution velocity, facility workload consumption, and team throughput evaluated in :tz.', ['tz' => $tenant->timezone ?: 'Europe/Athens']) }}
                        </p>
                        <div class="mt-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-mono font-medium bg-indigo-500/10 text-indigo-300 border border-indigo-500/30" title="{{ __('All SLA KPIs & reports are normalized to Organization Timezone') }}">
                                <span>🌐</span>
                                <span>{{ __('Org Baseline:') }} {{ $tenant->timezone ?: 'Europe/Athens' }} ({{ now()->timezone($tenant->timezone ?: 'Europe/Athens')->format('T') }})</span>
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        {{-- Period Filter Selector --}}
                        <div class="flex items-center gap-1 p-1 rounded-xl bg-slate-950 border border-slate-800 text-xs">
                            <button
                                wire:click="setAnalyticsPeriod('7d')"
                                type="button"
                                class="px-3 py-1.5 rounded-lg font-medium transition-all cursor-pointer {{ $analyticsPeriod === '7d' ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                            >
                                {{ __('Last 7 Days') }}
                            </button>
                            <button
                                wire:click="setAnalyticsPeriod('30d')"
                                type="button"
                                class="px-3 py-1.5 rounded-lg font-medium transition-all cursor-pointer {{ $analyticsPeriod === '30d' ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                            >
                                {{ __('Last 30 Days') }}
                            </button>
                            <button
                                wire:click="setAnalyticsPeriod('month')"
                                type="button"
                                class="px-3 py-1.5 rounded-lg font-medium transition-all cursor-pointer {{ $analyticsPeriod === 'month' ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                            >
                                {{ __('This Month') }}
                            </button>
                            <button
                                wire:click="setAnalyticsPeriod('all')"
                                type="button"
                                class="px-3 py-1.5 rounded-lg font-medium transition-all cursor-pointer {{ $analyticsPeriod === 'all' ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                            >
                                {{ __('All Time') }}
                            </button>
                        </div>

                        {{-- CSV Export Button --}}
                        <button
                            wire:click="exportAnalyticsCsv"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-400 border border-emerald-500/30 text-xs font-semibold shadow-lg shadow-emerald-950/40 transition-all cursor-pointer"
                        >
                            <span>⬇️</span>
                            <span>{{ __('Export CSV Report') }}</span>
                        </button>
                    </div>
                </div>

                {{-- SECTION A: Executive SLA KPI Cards (Top Row — 4 Cards) --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- Card 1: MTTA --}}
                    <div class="p-5 rounded-2xl bg-gradient-to-br from-cyan-500/10 via-slate-900/90 to-slate-900 border border-cyan-500/20 shadow-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-cyan-400 uppercase tracking-wider">{{ __('Mean Time to Start (MTTA)') }}</span>
                            <span class="text-xl">⏱️</span>
                        </div>
                        <div class="mt-2 text-2xl md:text-3xl font-extrabold text-white">
                            {{ $analytics['kpis']['mttaFormatted'] }}
                        </div>
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-800/80 text-[11px] text-slate-400">
                            <span>{{ __('Queue Wait Velocity') }}</span>
                            <span class="text-cyan-300 font-semibold">{{ __(':count started', ['count' => $analytics['kpis']['startedCount']]) }}</span>
                        </div>
                    </div>

                    {{-- Card 2: MTTR --}}
                    <div class="p-5 rounded-2xl bg-gradient-to-br from-emerald-500/10 via-slate-900/90 to-slate-900 border border-emerald-500/20 shadow-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">{{ __('Mean Time to Resolution (MTTR)') }}</span>
                            <span class="text-xl">⚡</span>
                        </div>
                        <div class="mt-2 text-2xl md:text-3xl font-extrabold text-white">
                            {{ $analytics['kpis']['mttrFormatted'] }}
                        </div>
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-800/80 text-[11px] text-slate-400">
                            <span>{{ __('Active Execution Speed') }}</span>
                            <span class="text-emerald-300 font-semibold">{{ __(':count resolved', ['count' => $analytics['kpis']['resolvedCount']]) }}</span>
                        </div>
                    </div>

                    {{-- Card 3: Completion vs Cancellation Rate --}}
                    <div class="p-5 rounded-2xl bg-gradient-to-br from-indigo-500/10 via-slate-900/90 to-slate-900 border border-indigo-500/20 shadow-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">{{ __('Completion Rate') }}</span>
                            <span class="text-xl">🎯</span>
                        </div>
                        <div class="mt-2 text-2xl md:text-3xl font-extrabold text-white">
                            {{ $analytics['kpis']['completionRate'] }}<span class="text-sm font-semibold text-indigo-400">%</span>
                        </div>
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-800/80 text-[11px] text-slate-400">
                            <span>{{ __(':completed done / :canceled canceled', ['completed' => $analytics['kpis']['completedCount'], 'canceled' => $analytics['kpis']['canceledCount']]) }}</span>
                            <span class="text-indigo-300 font-semibold">{{ $analytics['kpis']['totalDone'] }} {{ __('closed') }}</span>
                        </div>
                    </div>

                    {{-- Card 4: Urgent Request Ratio --}}
                    <div class="p-5 rounded-2xl bg-gradient-to-br from-rose-500/10 via-slate-900/90 to-slate-900 border border-rose-500/20 shadow-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-rose-400 uppercase tracking-wider">{{ __('Urgent Request Ratio') }}</span>
                            <span class="text-xl">🚨</span>
                        </div>
                        <div class="mt-2 text-2xl md:text-3xl font-extrabold text-white">
                            {{ $analytics['kpis']['urgentRatio'] }}<span class="text-sm font-semibold text-rose-400">%</span>
                        </div>
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-800/80 text-[11px] text-slate-400">
                            <span>{{ __(':count urgent/high', ['count' => $analytics['kpis']['urgentHighCount']]) }}</span>
                            <span class="text-rose-300 font-semibold">{{ $analytics['kpis']['totalRequests'] }} {{ __('total') }}</span>
                        </div>
                    </div>
                </div>

                {{-- SECTION B: Department Throughput & Bottlenecks --}}
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 backdrop-blur-xl shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-800/80 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                <span>🏢</span>
                                <span>{{ __('Department Throughput & Bottlenecks') }}</span>
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ __('Request volumes, active workload in queue, completed bounty, and average resolution speed per department.') }}
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider bg-slate-950/40">
                                    <th class="py-3 px-4 font-semibold">{{ __('Department') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Total Requests') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Active Queue') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Completed Tasks') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Bounty Delivered') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Resolution MTTR') }}</th>
                                    <th class="py-3 px-4 font-semibold">{{ __('Completion Velocity') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 text-slate-300">
                                @forelse ($analytics['departments'] as $dept)
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="py-3.5 px-4 font-medium text-white flex items-center gap-2.5">
                                            <span class="w-2.5 h-2.5 rounded-full ring-2 ring-white/10" style="background-color: {{ $dept['color'] }}"></span>
                                            <span>{{ $dept['name'] }}</span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-semibold text-slate-200">
                                            {{ $dept['totalRequests'] }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono">
                                            @if ($dept['activeQueue'] > 0)
                                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                                    {{ $dept['activeQueue'] }}
                                                </span>
                                            @else
                                                <span class="text-slate-500 font-mono">0</span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-semibold text-emerald-400">
                                            {{ $dept['completedTasks'] }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-semibold text-amber-400">
                                            ⚡ {{ number_format($dept['totalBounty']) }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-semibold text-cyan-300">
                                            {{ $dept['mttrFormatted'] }}
                                        </td>
                                        <td class="py-3.5 px-4 min-w-[160px]">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 h-2 rounded-full bg-slate-950 overflow-hidden border border-slate-800">
                                                    <div
                                                        class="h-full rounded-full transition-all duration-500 {{ $dept['completionRate'] >= 80 ? 'bg-emerald-500' : ($dept['completionRate'] >= 50 ? 'bg-indigo-500' : 'bg-amber-500') }}"
                                                        style="width: {{ $dept['completionRate'] }}%"
                                                    ></div>
                                                </div>
                                                <span class="text-[11px] font-mono text-slate-400 font-semibold w-9 text-right">{{ $dept['completionRate'] }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-8 text-center text-slate-500 italic">
                                            {{ __('No departments configured in this organization.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- SECTION C: Top Requesting Facilities / Client Units --}}
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 backdrop-blur-xl shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-800/80 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                <span>🏥</span>
                                <span>{{ __('Top Requesting Facilities & Client Demand') }}</span>
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ __('Capacity consumption breakdown by Facility, Hospital Unit, Branch, or B2B Client Organization.') }}
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider bg-slate-950/40">
                                    <th class="py-3 px-4 font-semibold">{{ __('Facility / Unit Name') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Total Requests') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Urgent / High') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Canceled / Self-Resolved') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Total Capacity Consumed') }}</th>
                                    <th class="py-3 px-4 font-semibold">{{ __('Share of Organization Capacity') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 text-slate-300">
                                @forelse ($analytics['facilities'] as $fac)
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="py-3.5 px-4 font-medium text-white flex items-center gap-2">
                                            <span>🏢</span>
                                            <span class="font-semibold">{{ $fac['name'] }}</span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-semibold text-slate-200">
                                            {{ $fac['totalRequests'] }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono">
                                            @if ($fac['urgentCount'] > 0)
                                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-500/15 text-rose-300 border border-rose-500/30">
                                                    {{ $fac['urgentCount'] }}
                                                </span>
                                            @else
                                                <span class="text-slate-500 font-mono">0</span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono text-slate-400">
                                            {{ $fac['canceledCount'] }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-bold text-amber-400">
                                            {{ $fac['totalWeight'] }} <span class="text-[10px] text-slate-400">pts</span>
                                        </td>
                                        <td class="py-3.5 px-4 min-w-[180px]">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 h-2 rounded-full bg-slate-950 overflow-hidden border border-slate-800">
                                                    <div
                                                        class="h-full rounded-full bg-gradient-to-r from-amber-500 to-indigo-500 transition-all duration-500"
                                                        style="width: {{ $fac['weightPercentage'] }}%"
                                                    ></div>
                                                </div>
                                                <span class="text-[11px] font-mono text-slate-400 font-semibold w-12 text-right">{{ $fac['weightPercentage'] }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-500 italic">
                                            {{ __('No requests recorded during the selected period.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- SECTION D: Worker SLA & Co-op Efficiency Breakdown --}}
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 backdrop-blur-xl shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-800/80 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                <span>⚡</span>
                                <span>{{ __('Worker SLA & Co-op Efficiency Breakdown') }}</span>
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ __('Solo resolutions, peer Co-op assists, period bounty earnings, and average resolution speed per collaborator.') }}
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider bg-slate-950/40">
                                    <th class="py-3 px-4 font-semibold">{{ __('Collaborator') }}</th>
                                    <th class="py-3 px-4 font-semibold">{{ __('Department') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Solo Completed') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Co-op Assists') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Bounty Earned in Period') }}</th>
                                    <th class="py-3 px-4 font-semibold text-center">{{ __('Execution Speed (MTTR)') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 text-slate-300">
                                @forelse ($analytics['workers'] as $w)
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="py-3.5 px-4 font-medium text-white flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-indigo-300">
                                                {{ strtoupper(substr($w['name'], 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-white">{{ $w['name'] }}</div>
                                                <div class="text-[10px] text-slate-400 capitalize">{{ $w['role'] }}</div>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            @if ($w['department'])
                                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-800 border border-slate-700 text-slate-200">
                                                    <span class="w-2 h-2 rounded-full" style="background-color: {{ $w['departmentColor'] ?? '#6366f1' }}"></span>
                                                    <span>{{ $w['department'] }}</span>
                                                </span>
                                            @else
                                                <span class="text-slate-500 italic text-[11px]">{{ __('General Pool') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-semibold text-emerald-400">
                                            {{ $w['soloCompleted'] }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-semibold text-purple-400">
                                            @if ($w['coopAssists'] > 0)
                                                <span>🤝 {{ $w['coopAssists'] }}</span>
                                            @else
                                                <span class="text-slate-500 font-mono">0</span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-bold text-amber-400">
                                            ⚡ {{ number_format($w['bountyEarned']) }} <span class="text-[10px] text-slate-400">pts</span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-semibold text-cyan-300">
                                            {{ $w['mttrFormatted'] }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-500 italic">
                                            {{ __('No active workers or coordinators in this organization.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </main>

    {{-- MODAL: Department Create / Edit --}}
    @if ($showDepartmentModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md shadow-2xl p-6 text-left">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>🏢</span>
                        <span>{{ $editingDepartmentId ? __('Edit Department') : __('Create Department') }}</span>
                    </h3>
                    <button wire:click="$set('showDepartmentModal', false)" class="text-slate-400 hover:text-white cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveDepartment" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Department Name') }}</label>
                        <input
                            wire:model.live.debounce.300ms="departmentName"
                            type="text"
                            placeholder="{{ __('e.g. Operations, Security, Legal') }}"
                            class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                        >
                        @error('departmentName') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Slug (Unique identifier)') }}</label>
                        <input
                            wire:model="departmentSlug"
                            type="text"
                            placeholder="{{ __('e.g. operations') }}"
                            class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                        >
                        @error('departmentSlug') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Brand / Radar Color') }}</label>
                        {{-- Color Swatches Grid --}}
                        <div class="grid grid-cols-5 gap-2 mb-3">
                            @foreach ($presetColors as $hex => $label)
                                <button
                                    type="button"
                                    wire:click="$set('departmentColor', '{{ $hex }}')"
                                    class="h-8 rounded-lg flex items-center justify-center transition-all cursor-pointer border {{ $departmentColor === $hex ? 'ring-2 ring-white border-white scale-105' : 'border-transparent hover:scale-105' }}"
                                    style="background-color: {{ $hex }}"
                                    title="{{ $label }} ({{ $hex }})"
                                >
                                    @if ($departmentColor === $hex)
                                        <svg class="w-4 h-4 text-white drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                wire:model="departmentColor"
                                type="text"
                                class="flex-1 rounded-xl bg-slate-950 border border-slate-800 px-3 py-1.5 text-xs text-white font-mono focus:border-indigo-500 focus:outline-none"
                            >
                            <div class="w-8 h-8 rounded-lg border border-slate-700" style="background-color: {{ $departmentColor }}"></div>
                        </div>
                        @error('departmentColor') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-3">
                        <button
                            wire:click="$set('showDepartmentModal', false)"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white cursor-pointer"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer"
                        >
                            {{ __('Save Department') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL: Member Create / Edit --}}
    @if ($showUserModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md shadow-2xl p-6 text-left">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>👤</span>
                        <span>{{ $editingUserId ? __('Edit Member') : __('Add Team Member') }}</span>
                    </h3>
                    <button wire:click="$set('showUserModal', false)" class="text-slate-400 hover:text-white cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveUser" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Full Name') }}</label>
                        <input
                            wire:model="userName"
                            type="text"
                            placeholder="{{ __('e.g. Maria Papadaki') }}"
                            class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                        >
                        @error('userName') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Email Address') }}</label>
                        <input
                            wire:model="userEmail"
                            type="email"
                            placeholder="{{ __('e.g. maria@company.com') }}"
                            class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                        >
                        @error('userEmail') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div x-data="{ showPass: false }">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                                {{ __('Password') }} {{ $editingUserId ? __('(leave blank to keep current)') : '' }}
                            </label>
                            <button
                                type="button"
                                @click="showPass = !showPass"
                                class="text-xs text-slate-400 hover:text-slate-200 transition-colors focus:outline-none flex items-center gap-1 cursor-pointer"
                                :title="showPass ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                            >
                                <span x-show="!showPass" class="text-xs">👁️ {{ __('Show') }}</span>
                                <span x-show="showPass" x-cloak class="text-xs">🙈 {{ __('Hide') }}</span>
                            </button>
                        </div>
                        <div class="relative">
                            <input
                                wire:model="userPassword"
                                :type="showPass ? 'text' : 'password'"
                                placeholder="••••••••"
                                class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                            >
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400 flex items-center gap-1">
                            <span>🔒</span>
                            <span>{{ __('Min 10 chars • Upper & Lowercase • Number • Symbol') }}</span>
                        </p>
                        @error('userPassword') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Role') }}</label>
                            <select
                                wire:model.live="userRole"
                                class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="worker">{{ __('Worker (Tantou)') }}</option>
                                <option value="coordinator">{{ __('Coordinator (Team Lead)') }}</option>
                                <option value="client">{{ __('Client (Requester)') }}</option>
                                <option value="admin">{{ __('Administrator') }}</option>
                            </select>
                            @error('userRole') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Max Capacity') }}</label>
                            <input
                                wire:model="userMaxCapacity"
                                type="number"
                                min="1"
                                max="50"
                                class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none font-mono"
                            >
                            @error('userMaxCapacity') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Department') }}</label>
                            <select
                                wire:model="userDepartmentId"
                                class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="">{{ __('No Department (General Pool)') }}</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                            @error('userDepartmentId') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Visibility') }}</label>
                            <select
                                wire:model="userVisibility"
                                @disabled($userRole === 'client')
                                class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none disabled:opacity-60 disabled:cursor-not-allowed disabled:bg-slate-900/80"
                            >
                                <option value="public">{{ __('Public (Clients & Team)') }}</option>
                                <option value="internal">{{ __('Internal (Team Only)') }}</option>
                                <option value="private">{{ __('Private (Owner Only)') }}</option>
                                <option value="none">{{ __('None (Hidden)') }}</option>
                            </select>
                            @if ($userRole === 'client')
                                <p class="text-[10px] text-amber-300/90 mt-1 flex items-start gap-1">
                                    <span class="shrink-0">🔒</span>
                                    <span>{{ __('Locked to None (Hidden) — Clients do not have a capacity board column.') }}</span>
                                </p>
                            @endif
                            @error('userVisibility') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                            @error('userBoardVisibility') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Facility / Clinical Unit / Client Org --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            {{ __('Facility / Unit / Client Org') }}
                        </label>
                        <input
                            wire:model="userFacilityName"
                            type="text"
                            placeholder="{{ __('e.g. Radiology, Branch B, Acme Corp') }}"
                            class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                        >
                        <p class="text-[10px] text-slate-500 mt-1">{{ __('Useful for scoping client requests or hospital departments.') }}</p>
                        @error('userFacilityName') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Worker Personal Timezone --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            {{ __('Preferred Personal Timezone') }}
                        </label>
                        <select
                            wire:model="userTimezone"
                            class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">{{ __('Inherit Workspace Default') }} ({{ $tenant->timezone ?: 'Europe/Athens' }})</option>
                            @foreach (\App\Support\Timezone::all() as $tzValue => $tzLabel)
                                <option value="{{ $tzValue }}">{{ $tzLabel }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-500 mt-1">{{ __('Optional override for remote workers in other timezones.') }}</p>
                        @error('userTimezone') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                            <input
                                wire:model="userIsAvailable"
                                type="checkbox"
                                class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-0"
                            >
                            <span>{{ __('Available / Currently Working 🟢 (uncheck if Away 🌙)') }}</span>
                        </label>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-3">
                        <button
                            wire:click="$set('showUserModal', false)"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white cursor-pointer"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer"
                        >
                            {{ __('Save Member') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL: PER-DEPARTMENT QR DROP-BOX GENERATOR & PRINT POSTER --}}
    @if ($showDepartmentQrModal)
        <div
            wire:key="dept-qr-modal-{{ $qrDepartmentSlug }}-{{ md5($qrDropBoxUrl) }}"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4"
            wire:keydown.escape.window="closeDepartmentQrModal"
            x-data="window.tantouDeptQrDropBox({
                url: @js($qrDropBoxUrl),
                deptName: @js($qrDepartmentName),
                deptSlug: @js($qrDepartmentSlug),
                deptColor: @js($qrDepartmentColor),
                workspaceName: @js($tenantName),
            })"
        >
            <div class="w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl overflow-hidden animate-fadeIn">
                {{-- Header --}}
                <div class="px-5 py-4 bg-slate-950/70 border-b border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span
                            class="w-3.5 h-3.5 rounded-full shrink-0 shadow-sm"
                            style="background-color: {{ $qrDepartmentColor }};"
                        ></span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold text-white truncate flex items-center gap-1.5">
                                <span>📱</span>
                                <span>{{ __('QR Drop-Box') }} — {{ $qrDepartmentName }}</span>
                            </h3>
                            <p class="text-[11px] text-slate-400 truncate">
                                {{ $tenantName }} · {{ __('Zero-Login Equipment & Unit Sticker') }}
                            </p>
                        </div>
                    </div>
                    <button
                        wire:click="closeDepartmentQrModal"
                        type="button"
                        class="text-slate-400 hover:text-white cursor-pointer shrink-0"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-5 space-y-4">
                    @if (! $qrPublicSubmissionEnabled)
                        <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-start gap-2">
                            <span class="shrink-0">⚠️</span>
                            <span>{{ __('Public Guest Drop-Box is currently disabled for this workspace. Enable it in Workspace Settings → Access & Drop-Box so guests can submit via this QR code.') }}</span>
                        </div>
                    @endif

                    {{-- QR Code Preview Card (wire:ignore preserves inline SVG across Livewire morphs) --}}
                    <div class="flex flex-col items-center justify-center p-5 rounded-2xl bg-slate-950/70 border border-slate-800">
                        <div
                            wire:ignore
                            x-ref="qrContainer"
                            class="p-3 rounded-2xl bg-white shadow-xl border border-slate-200"
                        >
                            {!! $qrSvgMarkup !!}
                        </div>
                        <div class="mt-3 text-center">
                            <p class="text-xs font-bold text-white">{{ $qrDepartmentName }}</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                {{ __('Scan with your phone camera to submit an IT / Support request — No login required') }}
                            </p>
                        </div>
                    </div>

                    {{-- Pre-filled Drop-Box URL --}}
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            {{ __('Pre-filled Department Drop-Box URL') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                readonly
                                value="{{ $qrDropBoxUrl }}"
                                class="w-full px-3 py-2 text-xs font-mono bg-slate-950 border border-slate-800 rounded-xl text-slate-200 select-all focus:outline-none focus:border-emerald-500/50"
                            >
                            <button
                                type="button"
                                @click.prevent="copyLink()"
                                class="shrink-0 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm shadow-emerald-900/30"
                            >
                                <span x-show="!copied">📋 {{ __('Copy Link') }}</span>
                                <span x-show="copied" x-cloak class="flex items-center gap-1">
                                    <span>✓</span>
                                    <span>{{ __('Copied!') }}</span>
                                </span>
                            </button>
                        </div>
                    </div>

                    {{-- Action Feedback / Error Toast --}}
                    <div
                        x-show="statusMessage"
                        x-cloak
                        class="px-3 py-2 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-medium flex items-center gap-2"
                    >
                        <span>✓</span>
                        <span x-text="statusMessage"></span>
                    </div>
                    <div
                        x-show="errorMessage"
                        x-cloak
                        class="px-3 py-2 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-medium flex items-center gap-2"
                    >
                        <span>⚠️</span>
                        <span x-text="errorMessage"></span>
                    </div>

                    {{-- One-Click Download & Print Poster Actions --}}
                    <div class="pt-3 border-t border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <button
                            type="button"
                            @click.prevent="downloadSvg()"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-100 text-xs font-semibold border border-slate-700 transition-all cursor-pointer"
                        >
                            <span x-show="!svgDownloaded">⬇️ {{ __('Download SVG') }}</span>
                            <span x-show="svgDownloaded" x-cloak class="text-emerald-300">✓ {{ __('Downloaded!') }}</span>
                        </button>
                        <button
                            type="button"
                            @click.prevent="downloadPng()"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-100 text-xs font-semibold border border-slate-700 transition-all cursor-pointer"
                        >
                            <span x-show="!pngDownloaded">⬇️ {{ __('Download PNG') }}</span>
                            <span x-show="pngDownloaded" x-cloak class="text-emerald-300">✓ {{ __('Downloaded!') }}</span>
                        </button>
                        <button
                            type="button"
                            @click.prevent="printPoster()"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer"
                        >
                            <span>🖨️</span>
                            <span>{{ __('Print QR Poster') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Dedicated @media print Poster Fallback (used automatically if browser blocks popup window) --}}
            <div id="dept-qr-print-fallback" aria-hidden="true">
                <div style="width: 100%; max-width: 520px; border: 3px solid #09090b; border-radius: 24px; padding: 32px 28px; text-align: center; background: #ffffff; color: #09090b;">
                    <div style="display: inline-flex; align-items: center; gap: 10px; padding: 6px 14px; border-radius: 999px; background: #09090b; color: #ffffff; font-weight: 800; font-size: 13px; letter-spacing: 0.04em; text-transform: uppercase;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background: #6366f1; color: #ffffff; font-size: 13px;">担当</span>
                        <span>Tantou · QR Drop-Box</span>
                    </div>
                    <div style="margin-top: 14px; font-size: 14px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.08em;">
                        {{ $tenantName }}
                    </div>
                    <h1 style="margin: 8px 0 18px; font-size: 34px; line-height: 1.15; font-weight: 900; color: #09090b;">
                        {{ $qrDepartmentName }}
                    </h1>
                    <div style="width: 72px; height: 6px; border-radius: 999px; margin: 0 auto 22px; background: {{ $qrDepartmentColor }};"></div>
                    <div wire:ignore style="display: inline-block; padding: 14px; border: 2px solid #e2e8f0; border-radius: 18px; background: #ffffff;">
                        {!! str_replace('id="dept-qr-code-svg"', 'id="dept-qr-code-svg-print"', $qrSvgMarkup) !!}
                    </div>
                    <div style="margin-top: 22px; padding-top: 18px; border-top: 2px dashed #cbd5e1;">
                        <p style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 8px;">Scan with your phone camera to submit an IT / Support request — No login required</p>
                        <p style="font-size: 14px; font-weight: 600; color: #334155; margin: 0;">Σαρώστε με την κάμερα του κινητού σας για να υποβάλετε αίτημα IT / Υποστήριξης — Χωρίς σύνδεση</p>
                    </div>
                    <div style="margin-top: 14px; font-size: 10px; font-family: monospace; color: #64748b; word-break: break-all;">
                        {{ $qrDropBoxUrl }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Workspace Settings Modal (Admin Only) --}}
    @include('livewire.partials.workspace-settings-modal')

    {{-- Telegram Pairing Modal --}}
    @include('livewire.partials.telegram-pairing-modal')
</div>
