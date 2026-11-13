<div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans selection:bg-indigo-500 selection:text-white">
    {{-- Top Navigation Bar --}}
    <header class="border-b border-slate-800/80 bg-slate-900/70 backdrop-blur-xl sticky top-0 z-30 px-6 py-3.5">
        <div class="max-w-[1920px] mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center space-x-3.5">
                <a href="{{ route('landing') }}" class="h-10 w-10 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-amber-400 flex items-center justify-center font-bold text-lg text-white shadow-lg shadow-indigo-500/20 hover:scale-105 transition-transform">
                    担
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-bold tracking-tight text-white">Tantou <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">担当</span></h1>
                        <span class="text-slate-600 text-sm">/</span>
                        <span class="text-slate-300 font-semibold text-sm">{{ $tenantName }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400">{{ __('Human Capacity OS & Workload Marketplace') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                {{-- Language Switcher (EN | ΕΛ) --}}
                <x-language-switcher />

                {{-- Navigation Switcher (Capacity Board vs Team & Leaderboard) --}}
                <nav class="flex items-center p-1 rounded-xl bg-slate-950/80 border border-slate-800 text-xs">
                    <a
                        href="{{ route('board') }}"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium text-slate-400 hover:text-slate-200 transition-all"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" /></svg>
                        <span>{{ __('Capacity Board') }}</span>
                    </a>

                    <a
                        href="{{ route('team') }}"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-semibold bg-indigo-600 text-white shadow-sm transition-all"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        <span>{{ __('Team & Leaderboard') }}</span>
                    </a>
                </nav>

                @auth
                    {{-- User Profile Indicator --}}
                    <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-slate-800/70 border border-slate-700/60 text-xs">
                        <div class="h-6 w-6 rounded-lg bg-indigo-500/20 text-indigo-300 font-bold flex items-center justify-center text-xs">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </div>
                        <div class="flex flex-col">
                            <span class="font-semibold text-slate-200 leading-tight">{{ auth()->user()->name }}</span>
                            <div class="flex items-center gap-1.5 text-[10px]">
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
                            <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">{{ __('Tantou Tasks Delivered') }}</span>
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
                                    <th class="py-3 px-4 text-center">{{ __('Tantou Tasks (Solo)') }}</th>
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
                                    <tr class="hover:bg-slate-800/40 transition-colors {{ $rank === 1 ? 'bg-amber-500/5' : '' }}">
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
                        <div class="p-5 rounded-2xl border border-slate-800 bg-slate-900/80 backdrop-blur-xl shadow-lg border-l-4 transition-all hover:border-slate-700" style="border-left-color: {{ $dept->color }}">
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

                            {{-- Admin Actions --}}
                            @if (auth()->user()?->role === 'admin')
                                <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
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
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    {{-- Member Name & Email --}}
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 font-bold flex items-center justify-center text-xs">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <span class="font-bold text-slate-100 block">{{ $user->name }}</span>
                                                <span class="text-[10px] text-slate-400">{{ $user->email }}</span>
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
                                        @if (auth()->user()?->role === 'admin')
                                            <select
                                                wire:change="quickUpdateVisibility({{ $user->id }}, $event.target.value)"
                                                class="text-[10px] bg-slate-950 border border-slate-700 rounded-lg px-2 py-0.5 text-slate-300 cursor-pointer focus:outline-none"
                                            >
                                                <option value="public" @selected($user->board_visibility === 'public')>{{ __('Public') }}</option>
                                                <option value="internal" @selected($user->board_visibility === 'internal')>{{ __('Internal') }}</option>
                                                <option value="private" @selected($user->board_visibility === 'private')>{{ __('Private') }}</option>
                                                <option value="none" @selected($user->board_visibility === 'none')>{{ __('None (Hide)') }}</option>
                                            </select>
                                        @else
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $user->board_visibility === 'public' ? 'bg-indigo-500/15 text-indigo-300 border border-indigo-500/30' : ($user->board_visibility === 'internal' ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30' : 'bg-purple-500/15 text-purple-300 border border-purple-500/30') }}">
                                                {{ $user->board_visibility }}
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
                                <tr>
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

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            {{ __('Password') }} {{ $editingUserId ? __('(leave blank to keep current)') : '' }}
                        </label>
                        <input
                            wire:model="userPassword"
                            type="password"
                            placeholder="••••••••"
                            class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                        >
                        @error('userPassword') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Role') }}</label>
                            <select
                                wire:model="userRole"
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
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">{{ __('Board Visibility') }}</label>
                            <select
                                wire:model="userBoardVisibility"
                                class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="public">{{ __('Public (Clients & Team)') }}</option>
                                <option value="internal">{{ __('Internal (Team Only)') }}</option>
                                <option value="private">{{ __('Private (Owner Only)') }}</option>
                                <option value="none">{{ __('None (Hidden)') }}</option>
                            </select>
                            @error('userBoardVisibility') <span class="text-[10px] text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>
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
</div>
