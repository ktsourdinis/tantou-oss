<div
    x-data="{
        draggingId: null,
        draggingIsClient: false,
        activeDropZone: null,
        openBacklog: true,
        openDone: true,
        didJustDrag: false,
        onDragStart(event, id, isClient) {
            this.draggingId = id;
            this.draggingIsClient = !!isClient;
            this.didJustDrag = true;
            event.dataTransfer.setData('text/plain', id);
            event.dataTransfer.effectAllowed = 'move';
        },
        onDragEnd() {
            this.draggingId = null;
            this.draggingIsClient = false;
            this.activeDropZone = null;
            setTimeout(() => { this.didJustDrag = false; }, 200);
        },
        onDragOver(zone, isBlocked = false) {
            if (isBlocked) {
                return;
            }
            this.activeDropZone = zone;
        },
        onDragLeave(zone) {
            if (this.activeDropZone === zone) {
                this.activeDropZone = null;
            }
        },
        onDrop(event, assigneeId, targetStatus, isBlocked = false) {
            if (isBlocked) {
                return;
            }
            const id = this.draggingId;
            const dropZoneEl = event ? event.currentTarget : null;
            this.activeDropZone = null;
            this.draggingId = null;
            this.draggingIsClient = false;
            if (id) {
                // Optimistic UI: instantly move card element to target container
                const cardEl = document.querySelector(`[data-post-it-id='${id}']`);
                if (cardEl && dropZoneEl && cardEl.parentElement !== dropZoneEl) {
                    const emptyPlaceholder = dropZoneEl.querySelector('.text-slate-500');
                    if (emptyPlaceholder && emptyPlaceholder.parentElement === dropZoneEl) {
                        emptyPlaceholder.style.display = 'none';
                    }
                    dropZoneEl.appendChild(cardEl);
                    cardEl.classList.add('opacity-75', 'ring-1', 'ring-indigo-500/60');
                }
                $wire.movePostIt(id, assigneeId, targetStatus);
            }
        }
    }"
    x-init="
        const currentUserId = {{ auth()->id() ?? 'null' }};
        if (window.Echo) {
            const channel = window.Echo.private('tenant.{{ $tenantId }}');
            const handleBoardUpdate = (e) => {
                if (e && e.actorUserId && currentUserId && e.actorUserId === currentUserId) {
                    return;
                }
                $wire.$refresh();
            };
            channel.listen('BoardUpdated', handleBoardUpdate);
            channel.listen('.BoardUpdated', handleBoardUpdate);
        }
    "
    class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans selection:bg-indigo-500 selection:text-white"
>
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

            {{-- Navigation Switcher (Capacity Board vs Team & Leaderboard) --}}
            <nav class="flex items-center p-1 rounded-xl bg-slate-950/80 border border-slate-800 text-xs">
                <a
                    href="{{ route('board') }}"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-semibold bg-indigo-600 text-white shadow-sm transition-all"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" /></svg>
                    <span>{{ __('Capacity Board') }}</span>
                </a>

                @if (auth()->user()?->role !== 'client')
                    <a
                        href="{{ route('team') }}"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium text-slate-400 hover:text-slate-200 transition-all"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        <span>{{ __('Team & Leaderboard') }}</span>
                    </a>
                @endif
            </nav>

            <div class="flex items-center gap-3">
                {{-- Language Switcher (EN | ΕΛ) --}}
                <x-language-switcher />

                {{-- Live Reverb Status Indicator --}}
                <div class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-950/60 border border-emerald-800/40 text-emerald-400 text-xs font-medium">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    {{ __('Reverb Sync Live') }}
                </div>

                {{-- View Density Toggle --}}
                <button
                    wire:click="toggleCompactView"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/60 hover:bg-slate-800 border border-slate-700/60 text-xs font-medium text-slate-300 transition-colors cursor-pointer"
                    title="{{ __('Toggle Card Density') }}"
                >
                    <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" /></svg>
                    <span>{{ $isCompact ? __('Compact') : __('Detailed') }}</span>
                </button>

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
                                    {{ auth()->user()->role }}
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

                {{-- Create Post-It Button --}}
                <button
                    wire:click="$set('showCreateModal', true)"
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>{{ __('New Post-it') }}</span>
                </button>
            </div>
        </div>
    </header>

    {{-- Department Radar Bar --}}
    <div class="border-b border-slate-800/60 bg-slate-900/40 px-6 py-2.5">
        <div class="max-w-[1920px] mx-auto flex items-center justify-between gap-4 overflow-x-auto pb-1 sm:pb-0">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5 mr-2 shrink-0">
                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-400"></span>
                    {{ __('Radar:') }}
                </span>

                @foreach ($radarTabs as $tab)
                    @php
                        $isSelected = $selectedDepartmentId === $tab['id'];
                        $statusBadgeClass = match($tab['status']) {
                            'danger' => 'bg-rose-500/20 text-rose-300 border-rose-500/40',
                            'warning' => 'bg-amber-500/20 text-amber-300 border-amber-500/40',
                            default => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                        };
                        $dotClass = match($tab['status']) {
                            'danger' => 'bg-rose-500 animate-pulse',
                            'warning' => 'bg-amber-400',
                            default => 'bg-emerald-400',
                        };
                    @endphp
                    <button
                        wire:click="selectDepartment({{ $tab['id'] !== null ? $tab['id'] : 'null' }})"
                        type="button"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all shrink-0 cursor-pointer {{ $isSelected ? 'bg-slate-800 text-white border border-indigo-500/50 shadow-md shadow-indigo-500/10' : 'bg-slate-950/60 text-slate-400 hover:text-slate-200 hover:bg-slate-900 border border-slate-800/80' }}"
                    >
                        <span class="h-2 w-2 rounded-full {{ $dotClass }}"></span>
                        <span>{{ $tab['name'] }}</span>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-mono border {{ $statusBadgeClass }}">
                            {{ $tab['current_capacity'] }}/{{ $tab['max_capacity'] }} ({{ $tab['ratio'] }}%)
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="hidden lg:flex items-center gap-4 text-[11px] text-slate-400 shrink-0">
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-400"></span> {{ __('Healthy (<70%)') }}</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-400"></span> {{ __('Busy (70-99%)') }}</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span> {{ __('Max (>=100%)') }}</span>
            </div>
        </div>
    </div>

    {{-- Main Kanban Canvas Layout with Collapsible Backlog & Done Drawers --}}
    <main class="flex-1 p-6 overflow-x-auto">
        <div class="max-w-[1920px] mx-auto flex gap-5 items-start">

            {{-- 1. BACKLOG DRAWER (Left) --}}
            <div
                :class="openBacklog ? 'w-80 shrink-0' : 'w-12 shrink-0'"
                class="transition-all duration-300 flex flex-col"
            >
                <div class="rounded-2xl bg-slate-900/60 border border-slate-800/70 p-3.5 flex flex-col shadow-xl">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800/80 mb-3">
                        <div x-show="openBacklog" class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-200 uppercase tracking-wider">{{ __('Unassigned') }}</span>
                            <span class="px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 text-[11px] font-bold">
                                {{ $backlogPostIts->count() }}
                            </span>
                        </div>
                        <button
                            @click="openBacklog = !openBacklog"
                            type="button"
                            class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer"
                            :title="openBacklog ? '{{ __('Collapse Backlog') }}' : '{{ __('Expand Backlog') }}'"
                        >
                            <svg x-show="openBacklog" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                            <svg x-show="!openBacklog" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>

                    {{-- Collapsed Indicator --}}
                    <div x-show="!openBacklog" class="flex flex-col items-center py-6 gap-3 cursor-pointer" @click="openBacklog = true">
                        <span class="[writing-mode:vertical-rl] text-xs font-bold text-slate-400 tracking-wider uppercase rotate-180">
                            {{ __('Backlog') }} ({{ $backlogPostIts->count() }})
                        </span>
                    </div>

                    {{-- Expanded Card List Drop Zone --}}
                    <div
                        x-show="openBacklog"
                        @dragover.prevent="onDragOver('backlog')"
                        @dragleave="onDragLeave('backlog')"
                        @drop.prevent="onDrop($event, null, 'backlog')"
                        :class="activeDropZone === 'backlog' ? 'border-indigo-500 bg-indigo-950/20' : 'border-transparent'"
                        class="space-y-3 min-h-[300px] rounded-xl border-2 border-dashed p-1 transition-all"
                    >
                        @forelse ($backlogPostIts as $postIt)
                            @include('livewire.partials.post-it-card', ['postIt' => $postIt, 'zone' => 'backlog'])
                        @empty
                            <div class="py-12 px-4 text-center border border-dashed border-slate-800 rounded-xl text-slate-500 text-xs">
                                {{ __('Backlog is clear!') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- 2. WORKER CAPACITY COLUMNS (Center / Marketplace) --}}
            <div class="flex-1 flex gap-5 overflow-x-auto pb-4">
                @forelse ($workers as $worker)
                    @php
                        $loadRatio = $worker->max_capacity > 0 ? ($worker->current_capacity / $worker->max_capacity) * 100 : 0;
                        $isHighLoad = $worker->current_capacity >= $worker->max_capacity;
                        $isBusy = $worker->current_capacity >= ($worker->max_capacity * 0.7);
                        $isAway = ! $worker->is_available;

                        $progressGradient = $isHighLoad
                            ? 'from-rose-600 via-rose-500 to-red-500'
                            : ($isBusy ? 'from-amber-500 to-amber-400' : 'from-emerald-500 to-teal-400');

                        $headerBg = $isHighLoad
                            ? 'bg-rose-950/30 border-rose-800/40'
                            : ($isBusy ? 'bg-amber-950/20 border-amber-800/30' : 'bg-slate-900/60 border-slate-800/70');

                        $canToggleAvailability = auth()->user() && (
                            auth()->user()->role === 'admin' ||
                            (int) auth()->user()->id === (int) $worker->id ||
                            (auth()->user()->role === 'coordinator' && $worker->department_id !== null && (int) auth()->user()->department_id === (int) $worker->department_id)
                        );

                        $canChangeVisibility = auth()->user() && (
                            auth()->user()->role === 'admin' ||
                            (int) auth()->user()->id === (int) $worker->id ||
                            (auth()->user()->role === 'coordinator' && $worker->department_id !== null && (int) auth()->user()->department_id === (int) $worker->department_id)
                        );
                    @endphp

                    <div class="w-64 shrink-0 flex flex-col rounded-2xl border {{ $headerBg }} p-3.5 shadow-xl transition-all {{ $isAway ? 'opacity-60 saturate-50' : '' }}">
                        {{-- Worker Header --}}
                        <div class="pb-3 border-b border-slate-800/80 mb-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="h-7 w-7 rounded-lg bg-indigo-500/20 text-indigo-300 font-bold flex items-center justify-center text-xs shrink-0">
                                        {{ substr($worker->name, 0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-xs font-bold text-white truncate flex items-center gap-1.5">
                                            <span>{{ $worker->name }}</span>
                                            @if ($isAway)
                                                <span class="px-1 py-0.2 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[9px] font-bold">
                                                    {{ __('AWAY') }}
                                                </span>
                                            @endif
                                        </h3>
                                        @if ($worker->department)
                                            <span class="text-[10px] text-slate-400 truncate block">{{ $worker->department->name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 {{ $isHighLoad ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30 animate-pulse' : ($isBusy ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30') }}">
                                    {{ $worker->current_capacity }}/{{ $worker->max_capacity }}
                                </span>
                            </div>

                            {{-- Availability Toggle & Column Visibility Controls --}}
                            <div class="flex items-center justify-between gap-1.5 pt-1 text-[10px]">
                                {{-- Working / Away Interactive Toggle --}}
                                @if ($canToggleAvailability)
                                    <button
                                        wire:click="toggleAvailability({{ $worker->id }})"
                                        type="button"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg font-semibold border transition-all cursor-pointer {{ $worker->is_available ? 'bg-emerald-950/60 text-emerald-300 border-emerald-800/50 hover:bg-emerald-900/60' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700' }}"
                                        title="{{ $worker->is_available ? __('Click to set status to Away 🌙') : __('Click to set status to Working 🟢') }}"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full {{ $worker->is_available ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                                        <span>{{ $worker->is_available ? __('Working 🟢') : __('Away 🌙') }}</span>
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-lg font-medium {{ $worker->is_available ? 'text-emerald-400' : 'text-slate-400' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $worker->is_available ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                                        <span>{{ $worker->is_available ? __('Working') : __('Away') }}</span>
                                    </span>
                                @endif

                                {{-- Column Mode (Public / Internal / Private) --}}
                                @if ($canChangeVisibility)
                                    <select
                                        wire:change="updateBoardVisibility({{ $worker->id }}, $event.target.value)"
                                        class="text-[10px] bg-slate-950 border border-slate-700 rounded-lg px-1.5 py-0.5 text-slate-300 cursor-pointer focus:outline-none"
                                        title="{{ __('Column visibility mode') }}"
                                    >
                                        <option value="public" @selected($worker->board_visibility === 'public')>{{ __('Public') }}</option>
                                        <option value="internal" @selected($worker->board_visibility === 'internal')>{{ __('Internal') }}</option>
                                        <option value="private" @selected($worker->board_visibility === 'private')>{{ __('Private') }}</option>
                                        <option value="none" @selected($worker->board_visibility === 'none')>{{ __('Hidden (None)') }}</option>
                                    </select>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $worker->board_visibility === 'public' ? 'bg-indigo-500/10 text-indigo-300 border border-indigo-500/20' : ($worker->board_visibility === 'internal' ? 'bg-amber-500/10 text-amber-300 border border-amber-500/20' : 'bg-purple-500/10 text-purple-300 border border-purple-500/20') }}">
                                        {{ $worker->board_visibility }}
                                    </span>
                                @endif
                            </div>

                            {{-- Capacity Load Bar --}}
                            <div class="w-full h-1.5 bg-slate-950 rounded-full overflow-hidden mt-1">
                                <div
                                    class="h-full bg-gradient-to-r {{ $progressGradient }} rounded-full transition-all duration-500"
                                    style="width: {{ min(100, $loadRatio) }}%;"
                                ></div>
                            </div>
                        </div>

                        {{-- Worker Drop Zone (Assigned & Doing Columns) --}}
                        @php
                            $isNonPublic = $worker->board_visibility !== 'public';
                        @endphp
                        <div
                            @dragover.prevent="onDragOver('worker-{{ $worker->id }}', {{ $isNonPublic ? 'draggingIsClient' : 'false' }})"
                            @dragleave="onDragLeave('worker-{{ $worker->id }}')"
                            @drop.prevent="onDrop($event, {{ $worker->id }}, 'assigned', {{ $isNonPublic ? 'draggingIsClient' : 'false' }})"
                            :class="[
                                activeDropZone === 'worker-{{ $worker->id }}' ? 'border-indigo-500 bg-indigo-950/20' : 'border-transparent',
                                (draggingIsClient && {{ $isNonPublic ? 'true' : 'false' }}) ? 'opacity-30 border-rose-500/40 cursor-not-allowed' : ''
                            ]"
                            class="space-y-3 min-h-[300px] rounded-xl border-2 border-dashed p-1 transition-all"
                        >
                            @forelse ($worker->assignedPostIts as $postIt)
                                @include('livewire.partials.post-it-card', ['postIt' => $postIt, 'zone' => 'doing', 'worker' => $worker])
                            @empty
                                <div class="py-12 px-3 text-center border border-dashed border-slate-800/80 rounded-xl text-slate-500 text-xs">
                                    {{ $isAway ? __('Collaborator is away') : __('Capacity available') }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="flex-1 py-16 text-center border border-dashed border-slate-800 rounded-2xl text-slate-400 text-sm">
                        {{ __('No active collaborator columns match your view filter.') }}
                    </div>
                @endforelse
            </div>

            {{-- 3. DONE DRAWER (Right) --}}
            <div
                :class="openDone ? 'w-80 shrink-0' : 'w-12 shrink-0'"
                class="transition-all duration-300 flex flex-col"
            >
                <div class="rounded-2xl bg-slate-900/60 border border-slate-800/70 p-3.5 flex flex-col shadow-xl">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800/80 mb-3">
                        <button
                            @click="openDone = !openDone"
                            type="button"
                            class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer"
                            :title="openDone ? '{{ __('Collapse Completed') }}' : '{{ __('Expand Completed') }}'"
                        >
                            <svg x-show="openDone" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            <svg x-show="!openDone" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>

                        <div x-show="openDone" class="flex items-center gap-2">
                            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">{{ __('Completed') }}</span>
                            <span class="px-2 py-0.5 rounded-full bg-emerald-950/60 text-emerald-300 border border-emerald-800/40 text-[11px] font-bold">
                                {{ $donePostIts->count() }}
                            </span>
                        </div>
                    </div>

                    {{-- Collapsed Indicator --}}
                    <div x-show="!openDone" class="flex flex-col items-center py-6 gap-3 cursor-pointer" @click="openDone = true">
                        <span class="[writing-mode:vertical-rl] text-xs font-bold text-emerald-400 tracking-wider uppercase rotate-180">
                            {{ __('Completed') }} ({{ $donePostIts->count() }})
                        </span>
                    </div>

                    {{-- Expanded Card List Drop Zone --}}
                    <div
                        x-show="openDone"
                        @dragover.prevent="onDragOver('done')"
                        @dragleave="onDragLeave('done')"
                        @drop.prevent="onDrop($event, null, 'done')"
                        :class="activeDropZone === 'done' ? 'border-emerald-500 bg-emerald-950/20' : 'border-transparent'"
                        class="space-y-3 min-h-[300px] rounded-xl border-2 border-dashed p-1 transition-all"
                    >
                        @forelse ($donePostIts as $postIt)
                            @include('livewire.partials.post-it-card', ['postIt' => $postIt, 'zone' => 'done'])
                        @empty
                            <div class="py-12 px-4 text-center border border-dashed border-slate-800 rounded-xl text-slate-500 text-xs">
                                {{ __('No completed tasks yet.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </main>

    {{-- MODAL 1: Create Post-It Modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-3 bg-slate-950/80 backdrop-blur-md">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg shadow-2xl p-4 sm:p-5 text-left">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="h-7 w-7 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold text-xs">
                            担
                        </div>
                        <h3 class="text-base font-bold text-white">{{ __('Create New Post-it') }}</h3>
                    </div>
                    <button
                        wire:click="$set('showCreateModal', false)"
                        class="text-slate-400 hover:text-white transition-colors cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form wire:submit="createPostIt" class="mt-3 space-y-3">
                    {{-- Title --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-300 uppercase tracking-wider mb-1">{{ __('Title') }}</label>
                        <input
                            wire:model="title"
                            type="text"
                            required
                            placeholder="{{ __('e.g. Integrate Stripe Webhook Handlers') }}"
                            class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-3 py-1.5 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/20"
                        >
                        @error('title') <span class="text-rose-400 text-xs mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Description (compact 2 rows) --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-300 uppercase tracking-wider mb-1">{{ __('Description (optional)') }}</label>
                        <textarea
                            wire:model="description"
                            rows="2"
                            placeholder="{{ __('Provide concise context or acceptance criteria...') }}"
                            class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/20 leading-relaxed"
                        ></textarea>
                    </div>

                    {{-- Tight 2x2 Grid: Department, Collaborator, Priority, Weight --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        {{-- Department --}}
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-300 uppercase tracking-wider mb-1">{{ __('Department') }}</label>
                            <select
                                wire:model="departmentId"
                                class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-2.5 py-1.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="">{{ __('Select Department...') }}</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Collaborator (Tantou) --}}
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-300 uppercase tracking-wider mb-1">
                                {{ __('Collaborator (Tantou)') }}
                            </label>
                            <select
                                wire:model="assigneeId"
                                class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-2.5 py-1.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="">{{ __('None — Send to Backlog') }}</option>
                                @foreach ($availableCollaborators as $collab)
                                    @php
                                        $collabStatus = $collab->current_capacity >= $collab->max_capacity
                                            ? __('High Load')
                                            : ($collab->current_capacity >= ($collab->max_capacity * 0.7) ? __('Busy') : __('Healthy'));
                                    @endphp
                                    <option value="{{ $collab->id }}">
                                        {{ $collab->name }} ({{ $collab->current_capacity }}/{{ $collab->max_capacity }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Priority --}}
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-300 uppercase tracking-wider mb-1">{{ __('Priority') }}</label>
                            <select
                                wire:model.live="priority"
                                class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-2.5 py-1.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="low">{{ __('Low (0.8x)') }}</option>
                                <option value="normal">{{ __('Normal (1.0x)') }}</option>
                                <option value="high">{{ __('High (1.25x)') }}</option>
                                <option value="urgent">{{ __('Urgent (1.5x)') }}</option>
                            </select>
                        </div>

                        {{-- Cognitive Weight --}}
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-300 uppercase tracking-wider mb-1">
                                {{ __('Cognitive Load / Weight') }}
                            </label>
                            <select
                                wire:model.live="weight"
                                class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-2.5 py-1.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="1">W1 - {{ __('Low') }} (10 {{ __('pts') }})</option>
                                <option value="2">W2 - {{ __('Light') }} (20 {{ __('pts') }})</option>
                                <option value="3">W3 - {{ __('Medium') }} (30 {{ __('pts') }})</option>
                                <option value="4">W4 - {{ __('High') }} (40 {{ __('pts') }})</option>
                                <option value="5">W5 - {{ __('Max') }} (50 {{ __('pts') }})</option>
                            </select>
                        </div>
                    </div>

                    {{-- Drag-and-Drop Attachment Zone (Slim 1-line bar) --}}
                    <div
                        x-data="{
                            isDragging: false,
                            isUploading: false,
                            progress: 0,
                            uploadError: null,
                            handleFiles(fileList) {
                                if (!fileList || fileList.length === 0) return;
                                this.isUploading = true;
                                this.uploadError = null;
                                $wire.uploadMultiple(
                                    'newAttachments',
                                    fileList,
                                    () => { this.isUploading = false; this.progress = 0; },
                                    (error) => { this.isUploading = false; this.uploadError = '{{ __('Upload failed') }}'; console.error(error); },
                                    (event) => { this.progress = event.detail.progress; }
                                );
                            }
                        }"
                        class="pt-1"
                    >
                        <div
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="isDragging = false; handleFiles($event.dataTransfer.files)"
                            @click="$refs.modalFileInput.click()"
                            :class="isDragging ? 'border-indigo-400 bg-indigo-950/40 text-indigo-300' : 'border-slate-800 hover:border-slate-700 bg-slate-950/50 text-slate-400'"
                            class="border border-dashed rounded-xl py-2 px-3 text-center cursor-pointer transition-all flex items-center justify-between gap-2"
                        >
                            <div class="flex items-center gap-2 text-xs min-w-0">
                                <span class="text-indigo-400 text-sm">📎</span>
                                <span class="truncate text-[11px]">{{ __('Drop files here or click to browse') }}</span>
                            </div>
                            <span class="text-[10px] text-slate-500 shrink-0 font-mono">{{ __('Max 5 files (10MB)') }}</span>
                        </div>

                        <input
                            x-ref="modalFileInput"
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip"
                            class="hidden"
                            @change="handleFiles($event.target.files)"
                        >

                        <div x-show="isUploading" x-cloak class="mt-1">
                            <div class="flex items-center justify-between text-[10px] text-indigo-300 mb-0.5">
                                <span>{{ __('Uploading attachments...') }}</span>
                                <span x-text="progress + '%'"></span>
                            </div>
                            <div class="w-full bg-slate-800 rounded-full h-1 overflow-hidden">
                                <div class="bg-indigo-500 h-1 rounded-full transition-all duration-150" :style="'width: ' + progress + '%'"></div>
                            </div>
                        </div>

                        <div x-show="uploadError" x-cloak class="mt-1 text-[10px] text-rose-400" x-text="uploadError"></div>

                        @error('newAttachments')
                            <span class="text-[10px] text-rose-400 block mt-1">{{ $message }}</span>
                        @enderror
                        @error('newAttachments.*')
                            <span class="text-[10px] text-rose-400 block mt-1">{{ $message }}</span>
                        @enderror

                        @if (!empty($newAttachments))
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($newAttachments as $tempFile)
                                    <span class="px-2 py-0.5 rounded-md bg-indigo-950/60 border border-indigo-500/30 text-indigo-300 text-[10px] truncate max-w-[180px] flex items-center gap-1">
                                        <span>📄</span>
                                        <span class="truncate">{{ $tempFile->getClientOriginalName() }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="pt-2.5 border-t border-slate-800 flex items-center justify-end gap-2.5">
                        <button
                            wire:click="$set('showCreateModal', false)"
                            type="button"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-400 hover:text-white transition-colors cursor-pointer"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/25 transition-all cursor-pointer"
                        >
                            {{ __('Create Post-it') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL 2: Co-op / Assist Split Modal --}}
    @if ($showCoOpModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md shadow-2xl p-6 text-left">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🤝</span>
                        <h3 class="text-base font-bold text-white">{{ __('Co-op / Assist Assignment') }}</h3>
                    </div>
                    <button
                        wire:click="$set('showCoOpModal', false)"
                        class="text-slate-400 hover:text-white transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                @php
                    $coOpPostIt = $coOpPostItId ? \App\Models\PostIt::with('assignee')->find($coOpPostItId) : null;
                    $eligibleHelpers = $this->getEligibleHelpers($coOpPostIt);
                @endphp

                <div class="mt-4 space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">{{ __('Select Co-op Helper') }}</label>
                            @if ($coOpPostIt?->assignee)
                                <span class="text-[10px] text-purple-300 font-semibold px-2 py-0.5 rounded bg-purple-500/10 border border-purple-500/20">
                                    {{ ucfirst($coOpPostIt->assignee->board_visibility) }} {{ __('mode matching') }}
                                </span>
                            @endif
                        </div>
                        <select
                            wire:model="coOpHelperId"
                            class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-3.5 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">{{ __('No Co-op Helper (Solo Execution)') }}</option>
                            @foreach ($eligibleHelpers as $helperOption)
                                <option value="{{ $helperOption->id }}">
                                    {{ $helperOption->name }} ({{ $helperOption->current_capacity }}/{{ $helperOption->max_capacity }} cap) [{{ $helperOption->board_visibility }}]
                                </option>
                            @endforeach
                        </select>
                        @if ($eligibleHelpers->isEmpty())
                            <p class="text-[11px] text-amber-400 mt-1">
                                {{ __('No other collaborators match this column\'s mode.') }}
                            </p>
                        @endif
                    </div>

                    @if ($coOpHelperId)
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">{{ __('Helper Split Percentage (0–50%)') }}</label>
                                <span class="text-xs text-purple-400 font-bold">{{ $coOpHelperSplit }}%</span>
                            </div>

                            <input
                                wire:model.live="coOpHelperSplit"
                                type="range"
                                min="0"
                                max="50"
                                step="5"
                                class="w-full accent-purple-500 cursor-pointer"
                            >

                            <div class="flex justify-between text-[10px] text-slate-500 font-mono mt-1">
                                <span>0%</span>
                                <span>25%</span>
                                <span>50% ({{ __('Max') }})</span>
                            </div>

                            {{-- Live split preview --}}
                            <div class="mt-3 p-3 rounded-xl bg-slate-950/80 border border-purple-500/30 flex items-center justify-between text-xs">
                                <div>
                                    <span class="text-slate-400 block text-[10px]">{{ __('Tantou Assignee') }}</span>
                                    <span class="font-bold text-white">{{ 100 - $coOpHelperSplit }}% {{ __('of points') }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-purple-300 block text-[10px]">{{ __('Co-op Helper') }}</span>
                                    <span class="font-bold text-purple-400">{{ $coOpHelperSplit }}% {{ __('of points') }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-3">
                        <button
                            wire:click="$set('showCoOpModal', false)"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white transition-colors cursor-pointer"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            wire:click="saveCoOpSplit"
                            type="button"
                            class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold shadow-lg shadow-purple-600/30 transition-all cursor-pointer"
                        >
                            {{ __('Save Co-op Split') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Card Detail & Scope Calibration Slide-Over Drawer --}}
    @if ($showSlideOver && $this->selectedPostIt)
        @php
            $selected = $this->selectedPostIt;
            $calculator = app(\App\Services\BountyCalculator::class);
            $splitData = $calculator->calculateSplit($selected->bounty_points, $selected->helper_split, (bool) $selected->helper_id);
            $currentUser = auth()->user();
            $canCalibrate = $currentUser && ($currentUser->role === 'admin' || (int) $currentUser->id === (int) $selected->assignee_id) && $selected->status !== 'done';
            $canCancel = $currentUser && ($currentUser->role === 'admin' || ($selected->status === 'backlog' && (int) $currentUser->id === (int) $selected->requester_id));

            $priorityBadgeClasses = match($selected->priority) {
                'urgent' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                'high' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                'low' => 'bg-slate-500/20 text-slate-400 border-slate-500/30',
                default => 'bg-sky-500/20 text-sky-300 border-sky-500/30',
            };

            $statusBadgeClasses = match($selected->status) {
                'done' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                'doing' => 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
                default => 'bg-slate-500/20 text-slate-300 border-slate-500/30',
            };
        @endphp

        <div
            x-data="{ open: true }"
            x-show="open"
            @keydown.escape.window="$wire.closeSlideOver()"
            class="fixed inset-0 z-50 overflow-hidden"
            role="dialog"
            aria-modal="true"
        >
            {{-- Backdrop --}}
            <div
                x-show="open"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-slate-950/75 backdrop-blur-sm transition-opacity"
                wire:click="closeSlideOver"
            ></div>

            {{-- Slide-over Panel --}}
            <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div
                    x-show="open"
                    x-transition:enter="transform transition ease-in-out duration-300"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-200"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="w-screen max-w-md bg-slate-900/95 border-l border-slate-800 shadow-2xl flex flex-col backdrop-blur-xl"
                >
                    {{-- Header --}}
                    <div class="px-4 py-3 border-b border-slate-800 bg-slate-900/60 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            {{-- Status Pill --}}
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider border {{ $statusBadgeClasses }}">
                                {{ $selected->status }}
                            </span>

                            {{-- Priority Pill --}}
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider border {{ $priorityBadgeClasses }}">
                                {{ $selected->priority }}
                            </span>

                            {{-- Weight Pill --}}
                            <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 text-[11px] font-semibold border border-slate-700">
                                W{{ $selected->weight }}
                            </span>

                            {{-- Department Pill --}}
                            @if ($selected->department)
                                <span
                                    class="px-2 py-0.5 rounded text-[11px] font-semibold border truncate max-w-[120px]"
                                    style="background-color: {{ $selected->department->color }}20; color: {{ $selected->department->color }}; border-color: {{ $selected->department->color }}40;"
                                >
                                    {{ $selected->department->name }}
                                </span>
                            @endif

                            {{-- Bounty Points --}}
                            <span class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/30 text-[11px] font-bold">
                                ⚡ {{ $selected->bounty_points }} pts
                            </span>
                        </div>

                        <button
                            wire:click="closeSlideOver"
                            type="button"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer"
                            title="{{ __('Close (Esc)') }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    {{-- Scrollable Content Body --}}
                    <div class="flex-1 overflow-y-auto p-4 space-y-3 text-slate-200">
                        {{-- Title & Requester --}}
                        <div>
                            <h2 class="text-base font-bold text-white leading-snug">
                                {{ $selected->title }}
                            </h2>

                            <div class="mt-1 flex items-center gap-2 text-xs text-slate-400 flex-wrap">
                                <span class="flex items-center gap-1">
                                    <span class="text-slate-500">{{ __('Requester:') }}</span>
                                    <span class="text-indigo-300 font-semibold">{{ $selected->display_requester_name ?? $selected->requester?->name ?? 'User #'.$selected->requester_id }}</span>
                                    @if (!$selected->is_masked && $selected->requester)
                                        <span class="text-slate-600">({{ $selected->requester->email }})</span>
                                    @endif
                                </span>
                                @if ($selected->assignedBy && (int) $selected->assigned_by_id !== (int) $selected->requester_id)
                                    <span class="text-slate-600">•</span>
                                    <span class="flex items-center gap-1">
                                        <span class="text-slate-500">{{ __('Assigned by:') }}</span>
                                        <span class="text-indigo-300 font-semibold">{{ $selected->assignedBy->name }}</span>
                                    </span>
                                @endif
                                <span class="text-slate-600">•</span>
                                <span class="text-[11px]">{{ $selected->created_at->diffForHumans() }}</span>
                            </div>
                        </div>

                        {{-- Compact Description (if present) --}}
                        @if ($selected->description)
                            <div class="p-2.5 rounded-xl bg-slate-950/70 border border-slate-800/80">
                                <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line">{{ $selected->description }}</p>
                            </div>
                        @endif

                        {{-- Compact Metadata Strip (2 small rows) --}}
                        <div class="p-2.5 rounded-xl bg-slate-950/70 border border-slate-800 grid grid-cols-2 gap-2 text-xs">
                            {{-- Row 1, Col 1: Tantou (Assignee) --}}
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-6 h-6 rounded-lg bg-indigo-600/30 text-indigo-300 font-bold flex items-center justify-center text-[10px] shrink-0 border border-indigo-500/30">
                                    {{ $selected->assignee ? strtoupper(substr($selected->assignee->name, 0, 1)) : '?' }}
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[9px] text-slate-500 uppercase block font-semibold leading-tight">{{ __('Tantou') }}</span>
                                    <span class="font-semibold text-white truncate text-[11px] block">{{ $selected->assignee?->name ?? __('Unassigned') }}</span>
                                </div>
                            </div>

                            {{-- Row 1, Col 2: Co-op Assistant --}}
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-6 h-6 rounded-lg bg-purple-600/30 text-purple-300 font-bold flex items-center justify-center text-[10px] shrink-0 border border-purple-500/30">
                                    🤝
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[9px] text-slate-500 uppercase block font-semibold leading-tight">{{ __('Co-op') }}</span>
                                    <span class="font-semibold text-purple-300 truncate text-[11px] block">
                                        {{ $selected->helper ? $selected->helper->name.' ('.$selected->helper_split.'%)' : __('Solo') }}
                                    </span>
                                </div>
                            </div>

                            {{-- Row 2, Col 1: Execution Timeline --}}
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-6 h-6 rounded-lg bg-slate-800 text-slate-400 flex items-center justify-center text-[10px] shrink-0 border border-slate-700">
                                    ⏱️
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[9px] text-slate-500 uppercase block font-semibold leading-tight">{{ __('Timeline') }}</span>
                                    <span class="text-slate-300 text-[10px] truncate block font-mono">
                                        {{ $selected->started_at ? $selected->started_at->format('d/m H:i') : __('Not started') }} → {{ $selected->completed_at ? $selected->completed_at->format('d/m H:i') : __('In Progress') }}
                                    </span>
                                </div>
                            </div>

                            {{-- Row 2, Col 2: Bounty Breakdown --}}
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center text-[10px] shrink-0 border border-amber-500/30">
                                    ⚡
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[9px] text-slate-500 uppercase block font-semibold leading-tight">{{ __('Bounty') }}</span>
                                    <span class="font-bold text-amber-400 text-[11px] truncate block">
                                        {{ $selected->bounty_points }} pts <span class="text-[9px] font-normal text-slate-400">(W{{ $selected->weight }} · {{ $selected->priority }})</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Assignment & Workflow Chain Timeline --}}
                        <div class="p-4 rounded-2xl bg-slate-950/70 border border-slate-800 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🔀</span>
                                    <span>{{ __('Assignment & Workflow Chain') }}</span>
                                </h4>
                                @php
                                    $assignmentChain = $selected->getAssignmentChain();
                                @endphp
                                @if (count($assignmentChain) >= 2)
                                    <span class="text-[10px] font-semibold text-indigo-300 bg-indigo-950/60 border border-indigo-500/30 px-2 py-0.5 rounded-full">
                                        {{ implode(' → ', $assignmentChain) }}{{ $selected->status === 'done' ? ' → ✓' : '' }}
                                    </span>
                                @endif
                            </div>

                            @if ($selected->transfers->isEmpty())
                                <div class="py-4 text-center text-xs text-slate-500 italic">
                                    {{ __('No transfer history recorded for this request.') }}
                                </div>
                            @else
                                <div class="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-800">
                                    @foreach ($selected->transfers as $transfer)
                                        @php
                                            $dotClass = match($transfer->action) {
                                                'completed' => 'bg-emerald-500 ring-emerald-500/30',
                                                'started' => 'bg-indigo-500 ring-indigo-500/30',
                                                'transferred' => 'bg-purple-500 ring-purple-500/30',
                                                'unassigned' => 'bg-slate-500 ring-slate-500/30',
                                                default => 'bg-sky-500 ring-sky-500/30',
                                            };
                                        @endphp
                                        <div class="relative text-xs">
                                            <span class="absolute -left-6 top-1 h-2.5 w-2.5 rounded-full ring-4 {{ $dotClass }}"></span>
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-semibold text-white">
                                                    @if ($transfer->action === 'assigned')
                                                        {{ __('Assigned to') }} <span class="text-sky-300">{{ $transfer->toUser?->name ?? 'User #'.$transfer->to_user_id }}</span>
                                                    @elseif ($transfer->action === 'transferred')
                                                        {{ __('Transferred from') }} <span class="text-slate-300">{{ $transfer->fromUser?->name ?? 'User #'.$transfer->from_user_id }}</span> {{ __('to') }} <span class="text-purple-300">{{ $transfer->toUser?->name ?? 'User #'.$transfer->to_user_id }}</span>
                                                    @elseif ($transfer->action === 'started')
                                                        {{ __('Work started') }}
                                                    @elseif ($transfer->action === 'completed')
                                                        {{ __('Task completed') }}
                                                    @elseif ($transfer->action === 'unassigned')
                                                        {{ __('Returned to Backlog (Unassigned)') }}
                                                    @else
                                                        {{ ucfirst($transfer->action) }}
                                                    @endif
                                                </span>
                                                <span class="text-[10px] text-slate-500 shrink-0">
                                                    {{ $transfer->created_at?->format('d/m/Y H:i') }}
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1.5">
                                                <span class="text-slate-500">{{ __('By:') }}</span>
                                                <span class="text-indigo-300 font-medium">{{ $transfer->actor?->name ?? 'System' }}</span>
                                                <span class="text-slate-600">•</span>
                                                <span class="text-slate-500 text-[10px]">{{ $transfer->created_at?->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Scope Calibration (for Assignee or Admin while status != 'done') --}}
                        @if ($canCalibrate)
                            <div class="p-4 rounded-2xl bg-indigo-950/30 border border-indigo-500/30 space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold text-indigo-300 uppercase tracking-wider flex items-center gap-1.5">
                                        <span>⚙️</span>
                                        <span>{{ __('Scope Calibration') }}</span>
                                    </h4>
                                    <span class="text-[10px] text-slate-400">{{ __('Recalculate Bounty & Capacity') }}</span>
                                </div>

                                {{-- Weight Selector --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Cognitive Load / Weight (1–5)') }}</label>
                                    <div class="grid grid-cols-5 gap-1.5">
                                        @foreach ([1 => 'W1 - Low', 2 => 'W2 - Light', 3 => 'W3 - Med', 4 => 'W4 - High', 5 => 'W5 - Max'] as $wVal => $wLabel)
                                            <button
                                                type="button"
                                                wire:click="$set('slideOverWeight', {{ $wVal }})"
                                                class="px-2 py-1.5 rounded-xl text-xs font-bold border transition-all text-center cursor-pointer {{ $slideOverWeight === $wVal ? 'bg-indigo-600 text-white border-indigo-500 shadow-md' : 'bg-slate-900 text-slate-400 border-slate-800 hover:border-slate-700' }}"
                                            >
                                                {{ $wLabel }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Priority Selector --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Priority') }}</label>
                                    <div class="grid grid-cols-4 gap-1.5">
                                        @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $pVal => $pLabel)
                                            <button
                                                type="button"
                                                wire:click="$set('slideOverPriority', '{{ $pVal }}')"
                                                class="px-2 py-1.5 rounded-xl text-xs font-bold border transition-all text-center cursor-pointer capitalize {{ $slideOverPriority === $pVal ? 'bg-amber-600 text-white border-amber-500 shadow-md' : 'bg-slate-900 text-slate-400 border-slate-800 hover:border-slate-700' }}"
                                            >
                                                {{ $pLabel }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="flex justify-end pt-1">
                                    <button
                                        type="button"
                                        wire:click="saveScopeCalibration"
                                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-md shadow-indigo-600/30 transition-all cursor-pointer flex items-center gap-1.5"
                                    >
                                        <span>💾</span>
                                        <span>{{ __('Update Scope & Calibration') }}</span>
                                    </button>
                                </div>
                            </div>

                            @php
                                $slideOverEligibleHelpers = $this->getEligibleHelpers($selected);
                            @endphp

                            @if ($selected->assignee?->board_visibility === 'private')
                                <div class="p-4 rounded-2xl bg-purple-950/20 border border-purple-500/20 text-xs text-purple-300 italic">
                                    {{ __('🔒 Private columns do not support Co-op assist.') }}
                                </div>
                            @else
                                {{-- Co-op Assist Configuration inside Slide-over --}}
                                <div class="p-4 rounded-2xl bg-purple-950/30 border border-purple-500/30 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-xs font-bold text-purple-300 uppercase tracking-wider flex items-center gap-1.5">
                                            <span>🤝</span>
                                            <span>{{ __('Co-op Assist Configuration') }}</span>
                                        </h4>
                                        <span class="text-[10px] text-purple-300 font-medium px-2 py-0.5 rounded bg-purple-500/10 border border-purple-500/20">
                                            {{ ucfirst($selected->assignee?->board_visibility ?? 'Public') }} {{ __('mode') }}
                                        </span>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Select Co-op Helper') }}</label>
                                        <select
                                            wire:model="slideOverHelperId"
                                            class="w-full rounded-xl bg-slate-950 border border-slate-800 px-3.5 py-2 text-xs text-white focus:border-purple-500 focus:outline-none"
                                        >
                                            <option value="">{{ __('No Co-op Helper (Solo)') }}</option>
                                            @foreach ($slideOverEligibleHelpers as $collab)
                                                <option value="{{ $collab->id }}">
                                                    {{ $collab->name }} ({{ $collab->current_capacity }}/{{ $collab->max_capacity }} cap) [{{ $collab->board_visibility }}]
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($slideOverEligibleHelpers->isEmpty())
                                            <p class="text-[11px] text-amber-400 mt-1">
                                                {{ __('No other collaborators match this column\'s mode.') }}
                                            </p>
                                        @endif
                                    </div>

                                @if ($slideOverHelperId)
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <label class="block text-xs font-semibold text-slate-300">{{ __('Helper Split:') }} <span class="text-purple-400 font-bold">{{ $slideOverHelperSplit }}%</span></label>
                                            <span class="text-[10px] text-slate-400">{{ __('Tantou:') }} {{ 100 - $slideOverHelperSplit }}%</span>
                                        </div>
                                        <input
                                            wire:model.live="slideOverHelperSplit"
                                            type="range"
                                            min="0"
                                            max="50"
                                            step="5"
                                            class="w-full accent-purple-500 cursor-pointer"
                                        >
                                    </div>
                                @endif

                                <div class="flex justify-end pt-1">
                                    <button
                                        type="button"
                                        wire:click="saveSlideOverCoOp"
                                        class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-md shadow-purple-600/30 transition-all cursor-pointer flex items-center gap-1.5"
                                    >
                                        <span>🤝</span>
                                        <span>{{ __('Save Co-op Assist') }}</span>
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endif

                        {{-- File Attachments & Documents Panel --}}
                        <div class="p-4 rounded-2xl bg-slate-950/70 border border-slate-800 space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>📎</span>
                                    <span>{{ __('File Attachments') }} ({{ $selected->attachments->count() }})</span>
                                </h4>
                                <span class="text-[10px] text-slate-500 font-mono">{{ __('Secure tenant storage') }}</span>
                            </div>

                            {{-- Existing Attachments List --}}
                            <div class="space-y-2.5 max-h-56 overflow-y-auto pr-1">
                                @forelse ($selected->attachments as $attachment)
                                    @php
                                        $canDeleteAtt = $currentUser && (
                                            $currentUser->role === 'admin' ||
                                            (int) $currentUser->id === (int) $attachment->uploader_id ||
                                            (int) $currentUser->id === (int) $selected->assignee_id ||
                                            ($currentUser->role === 'coordinator' && (
                                                (int) $currentUser->department_id === (int) $selected->department_id ||
                                                (int) $currentUser->department_id === (int) $selected->assignee?->department_id
                                            ))
                                        );
                                    @endphp
                                    <div class="p-2.5 rounded-xl bg-slate-900/90 border border-slate-800 flex items-center justify-between gap-3 text-xs">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            @if ($attachment->isImage())
                                                <a href="{{ route('attachments.show', $attachment) }}" target="_blank" class="shrink-0 group">
                                                    <img
                                                        src="{{ route('attachments.show', $attachment) }}"
                                                        alt="{{ $attachment->original_name }}"
                                                        class="w-12 h-12 rounded-lg object-cover border border-slate-700 group-hover:ring-2 group-hover:ring-indigo-500 transition-all"
                                                        loading="lazy"
                                                    />
                                                </a>
                                            @else
                                                <div class="w-10 h-10 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center text-lg shrink-0 text-slate-400">
                                                    @if (str_contains($attachment->mime_type, 'pdf'))
                                                        📕
                                                    @elseif (str_contains($attachment->mime_type, 'zip'))
                                                        🗜️
                                                    @elseif (str_contains($attachment->mime_type, 'sheet') || str_contains($attachment->mime_type, 'excel') || str_contains($attachment->mime_type, 'csv'))
                                                        📊
                                                    @elseif (str_contains($attachment->mime_type, 'word') || str_contains($attachment->mime_type, 'document'))
                                                        📝
                                                    @else
                                                        📄
                                                    @endif
                                                </div>
                                            @endif

                                            <div class="min-w-0 flex-1">
                                                <p class="font-semibold text-slate-200 truncate text-[11px]" title="{{ $attachment->original_name }}">
                                                    {{ $attachment->original_name }}
                                                </p>
                                                <div class="flex items-center gap-2 text-[10px] text-slate-500 mt-0.5">
                                                    <span>{{ $attachment->formattedSize() }}</span>
                                                    <span>•</span>
                                                    <span>{{ $attachment->uploader?->name ?? 'User' }}</span>
                                                    <span>•</span>
                                                    <span>{{ $attachment->created_at->diffForHumans(null, true) }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 shrink-0">
                                            <a
                                                href="{{ route('attachments.show', ['attachment' => $attachment, 'download' => 1]) }}"
                                                class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-[11px] font-medium border border-slate-700 transition-colors flex items-center gap-1"
                                                title="{{ __('Download file') }}"
                                            >
                                                <span>⬇</span>
                                                <span class="hidden sm:inline">{{ __('Download') }}</span>
                                            </a>

                                            @if ($canDeleteAtt)
                                                <button
                                                    wire:click="deleteAttachment({{ $attachment->id }})"
                                                    wire:confirm="{{ __('Are you sure you want to delete this attachment?') }}"
                                                    type="button"
                                                    class="p-1 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 transition-colors cursor-pointer"
                                                    title="{{ __('Delete attachment') }}"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="py-4 text-center text-xs text-slate-500 italic">
                                        {{ __('No attachments on this post-it.') }}
                                    </div>
                                @endforelse
                            </div>

                            {{-- Drag-and-Drop Attachment Zone (Auto-Save on Drop/Select) --}}
                            <div
                                x-data="{
                                    isDragging: false,
                                    isUploading: false,
                                    progress: 0,
                                    uploadError: null,
                                    handleFiles(fileList) {
                                        if (!fileList || fileList.length === 0) return;
                                        this.isUploading = true;
                                        this.uploadError = null;
                                        $wire.uploadMultiple(
                                            'slideOverNewAttachments',
                                            fileList,
                                            () => { this.isUploading = false; this.progress = 0; },
                                            (error) => { this.isUploading = false; this.uploadError = '{{ __('Upload failed') }}'; console.error(error); },
                                            (event) => { this.progress = event.detail.progress; }
                                        );
                                    }
                                }"
                                class="pt-2 border-t border-slate-800"
                            >
                                <div
                                    @dragover.prevent="isDragging = true"
                                    @dragleave.prevent="isDragging = false"
                                    @drop.prevent="isDragging = false; handleFiles($event.dataTransfer.files)"
                                    @click="$refs.slideFileInput.click()"
                                    :class="isDragging ? 'border-indigo-400 bg-indigo-950/40 text-indigo-300' : 'border-slate-800 hover:border-slate-700 bg-slate-900/60 text-slate-400'"
                                    class="border border-dashed rounded-xl py-2 px-3 text-center cursor-pointer transition-all flex items-center justify-between gap-2"
                                >
                                    <div class="flex items-center gap-2 text-xs min-w-0">
                                        <span class="text-indigo-400 text-sm">📎</span>
                                        <span class="truncate text-[11px]">{{ __('Drop files here or click to auto-upload') }}</span>
                                    </div>
                                    <span class="text-[10px] text-slate-500 shrink-0 font-mono">{{ __('Auto-saved') }}</span>
                                </div>

                                <input
                                    x-ref="slideFileInput"
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip"
                                    class="hidden"
                                    @change="handleFiles($event.target.files)"
                                >

                                <div x-show="isUploading" x-cloak class="mt-1.5">
                                    <div class="flex items-center justify-between text-[10px] text-indigo-300 mb-0.5">
                                        <span>{{ __('Uploading & saving attachments...') }}</span>
                                        <span x-text="progress + '%'"></span>
                                    </div>
                                    <div class="w-full bg-slate-800 rounded-full h-1 overflow-hidden">
                                        <div class="bg-indigo-500 h-1 rounded-full transition-all duration-150" :style="'width: ' + progress + '%'"></div>
                                    </div>
                                </div>

                                <div x-show="uploadError" x-cloak class="mt-1 text-[10px] text-rose-400" x-text="uploadError"></div>

                                @error('slideOverNewAttachments')
                                    <span class="text-[10px] text-rose-400 block mt-1">{{ $message }}</span>
                                @enderror
                                @error('slideOverNewAttachments.*')
                                    <span class="text-[10px] text-rose-400 block mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        {{-- Real-Time Micro-Comments Thread --}}
                        <div class="p-4 rounded-2xl bg-slate-950/70 border border-slate-800 space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>💬</span>
                                    <span>{{ __('Micro-Comments Thread') }} ({{ $selected->comments->count() }})</span>
                                </h4>
                                <span class="text-[10px] text-slate-500 font-mono">{{ __('Real-time sync') }}</span>
                            </div>

                            {{-- Comments List --}}
                            <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                                @forelse ($selected->comments as $comment)
                                    <div class="p-3 rounded-xl bg-slate-900/90 border border-slate-800 text-xs space-y-1.5">
                                        <div class="flex items-center justify-between text-[11px]">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-indigo-500 to-indigo-700 text-white flex items-center justify-center font-bold text-[10px]">
                                                    {{ strtoupper(substr($comment->user?->name ?? 'U', 0, 1)) }}
                                                </div>
                                                <span class="font-semibold text-slate-200">{{ $comment->user?->name ?? 'User' }}</span>
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase tracking-wider bg-slate-800 text-slate-400">
                                                    {{ $comment->user?->role ?? 'user' }}
                                                </span>
                                            </div>
                                            <span class="text-slate-500 text-[10px]">{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-slate-300 pl-8 leading-relaxed whitespace-pre-line text-[11px]">
                                            {{ $comment->body }}
                                        </p>
                                    </div>
                                @empty
                                    <div class="py-6 text-center text-xs text-slate-500 italic">
                                        {{ __('No comments yet. Start the conversation!') }}
                                    </div>
                                @endforelse
                            </div>

                            {{-- Add New Comment Form --}}
                            <form wire:submit.prevent="addComment" class="space-y-2 pt-2 border-t border-slate-800">
                                <textarea
                                    wire:model="newCommentBody"
                                    rows="2"
                                    placeholder="{{ __('Write a comment, clarification or progress note...') }}"
                                    class="w-full rounded-xl bg-slate-900 border border-slate-800 px-3 py-2 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none resize-none"
                                ></textarea>
                                @error('newCommentBody')
                                    <span class="text-[10px] text-rose-400">{{ $message }}</span>
                                @enderror

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        wire:loading.attr="disabled"
                                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/30 transition-all cursor-pointer flex items-center gap-1.5"
                                    >
                                        <span wire:loading.remove wire:target="addComment">{{ __('Post Comment') }}</span>
                                        <span wire:loading wire:target="addComment">{{ __('Posting...') }}</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Footer: Close and Optional Cancel Request --}}
                    <div class="px-6 py-4 border-t border-slate-800 bg-slate-900/80 flex items-center justify-between shrink-0">
                        @if ($canCancel)
                            <button
                                wire:click="cancelPostIt"
                                wire:confirm="{{ __('Are you sure you want to cancel/delete this request?') }}"
                                type="button"
                                class="px-3.5 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/30 text-xs font-semibold transition-colors cursor-pointer"
                            >
                                🗑️ {{ __('Cancel Request') }}
                            </button>
                        @else
                            <div></div>
                        @endif

                        <button
                            wire:click="closeSlideOver"
                            type="button"
                            class="px-4 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors cursor-pointer"
                        >
                            {{ __('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
