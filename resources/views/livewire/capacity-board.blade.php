<div
    x-data="{
        draggingId: null,
        activeDropZone: null,
        openBacklog: true,
        openDone: true,
        onDragStart(event, id) {
            this.draggingId = id;
            event.dataTransfer.setData('text/plain', id);
            event.dataTransfer.effectAllowed = 'move';
        },
        onDragEnd() {
            this.draggingId = null;
            this.activeDropZone = null;
        },
        onDragOver(zone) {
            this.activeDropZone = zone;
        },
        onDragLeave(zone) {
            if (this.activeDropZone === zone) {
                this.activeDropZone = null;
            }
        },
        onDrop(assigneeId, targetStatus) {
            const id = this.draggingId;
            this.activeDropZone = null;
            this.draggingId = null;
            if (id) {
                $wire.movePostIt(id, assigneeId, targetStatus);
            }
        }
    }"
    x-init="
        if (window.Echo) {
            const channel = window.Echo.private('tenant.{{ $tenantId }}');
            channel.listen('BoardUpdated', () => {
                $wire.$refresh();
            });
            channel.listen('.BoardUpdated', () => {
                $wire.$refresh();
            });
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
                    <p class="text-[11px] text-slate-400">Human Capacity OS & Workload Marketplace</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                {{-- Live Reverb Status Indicator --}}
                <div class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-950/60 border border-emerald-800/40 text-emerald-400 text-xs font-medium">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    Reverb Sync Live
                </div>

                {{-- View Density Toggle --}}
                <button
                    wire:click="toggleCompactView"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/60 hover:bg-slate-800 border border-slate-700/60 text-xs font-medium text-slate-300 transition-colors cursor-pointer"
                    title="Toggle Card Density"
                >
                    <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" /></svg>
                    <span>{{ $isCompact ? 'Compact' : 'Detailed' }}</span>
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
                        title="Sign Out"
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
                    <span>New Post-it</span>
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
                    Radar:
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
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-400"></span> Healthy (&lt;70%)</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-400"></span> Busy (70-99%)</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span> Max (&gt;=100%)</span>
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
                            <span class="text-xs font-bold text-slate-200 uppercase tracking-wider">Unassigned</span>
                            <span class="px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 text-[11px] font-bold">
                                {{ $backlogPostIts->count() }}
                            </span>
                        </div>
                        <button
                            @click="openBacklog = !openBacklog"
                            type="button"
                            class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer"
                            :title="openBacklog ? 'Collapse Backlog' : 'Expand Backlog'"
                        >
                            <svg x-show="openBacklog" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                            <svg x-show="!openBacklog" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>

                    {{-- Collapsed Indicator --}}
                    <div x-show="!openBacklog" class="flex flex-col items-center py-6 gap-3 cursor-pointer" @click="openBacklog = true">
                        <span class="[writing-mode:vertical-rl] text-xs font-bold text-slate-400 tracking-wider uppercase rotate-180">
                            Backlog ({{ $backlogPostIts->count() }})
                        </span>
                    </div>

                    {{-- Expanded Card List Drop Zone --}}
                    <div
                        x-show="openBacklog"
                        @dragover.prevent="onDragOver('backlog')"
                        @dragleave="onDragLeave('backlog')"
                        @drop.prevent="onDrop(null, 'backlog')"
                        :class="activeDropZone === 'backlog' ? 'border-indigo-500 bg-indigo-950/20' : 'border-transparent'"
                        class="space-y-3 min-h-[300px] rounded-xl border-2 border-dashed p-1 transition-all"
                    >
                        @forelse ($backlogPostIts as $postIt)
                            @include('livewire.partials.post-it-card', ['postIt' => $postIt, 'zone' => 'backlog'])
                        @empty
                            <div class="py-12 px-4 text-center border border-dashed border-slate-800 rounded-xl text-slate-500 text-xs">
                                Backlog is clear!
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

                        $progressGradient = $isHighLoad
                            ? 'from-rose-600 via-rose-500 to-red-500'
                            : ($isBusy ? 'from-amber-500 to-amber-400' : 'from-emerald-500 to-teal-400');

                        $headerBg = $isHighLoad
                            ? 'bg-rose-950/30 border-rose-800/40'
                            : ($isBusy ? 'bg-amber-950/20 border-amber-800/30' : 'bg-slate-900/60 border-slate-800/70');
                    @endphp

                    <div class="w-64 shrink-0 flex flex-col rounded-2xl border {{ $headerBg }} p-3.5 shadow-xl transition-all">
                        {{-- Worker Header --}}
                        <div class="pb-3 border-b border-slate-800/80 mb-3">
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="h-7 w-7 rounded-lg bg-indigo-500/20 text-indigo-300 font-bold flex items-center justify-center text-xs shrink-0">
                                        {{ substr($worker->name, 0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-xs font-bold text-white truncate">{{ $worker->name }}</h3>
                                        @if ($worker->department)
                                            <span class="text-[10px] text-slate-400 truncate block">{{ $worker->department->name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $isHighLoad ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30 animate-pulse' : ($isBusy ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30') }}">
                                    {{ $worker->current_capacity }}/{{ $worker->max_capacity }}
                                </span>
                            </div>

                            {{-- Capacity Load Bar --}}
                            <div class="w-full h-1.5 bg-slate-950 rounded-full overflow-hidden mt-2">
                                <div
                                    class="h-full bg-gradient-to-r {{ $progressGradient }} rounded-full transition-all duration-500"
                                    style="width: {{ min(100, $loadRatio) }}%;"
                                ></div>
                            </div>
                        </div>

                        {{-- Worker Drop Zone (Doing Column) --}}
                        <div
                            @dragover.prevent="onDragOver('worker-{{ $worker->id }}')"
                            @dragleave="onDragLeave('worker-{{ $worker->id }}')"
                            @drop.prevent="onDrop({{ $worker->id }}, 'doing')"
                            :class="activeDropZone === 'worker-{{ $worker->id }}' ? 'border-indigo-500 bg-indigo-950/20' : 'border-transparent'"
                            class="space-y-3 min-h-[300px] rounded-xl border-2 border-dashed p-1 transition-all"
                        >
                            @forelse ($worker->assignedPostIts as $postIt)
                                @include('livewire.partials.post-it-card', ['postIt' => $postIt, 'zone' => 'doing', 'worker' => $worker])
                            @empty
                                <div class="py-12 px-3 text-center border border-dashed border-slate-800/80 rounded-xl text-slate-500 text-xs">
                                    Capacity available
                                </div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="flex-1 py-16 text-center border border-dashed border-slate-800 rounded-2xl text-slate-400 text-sm">
                        No workers found in this department.
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
                            :title="openDone ? 'Collapse Completed' : 'Expand Completed'"
                        >
                            <svg x-show="openDone" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            <svg x-show="!openDone" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>

                        <div x-show="openDone" class="flex items-center gap-2">
                            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Completed</span>
                            <span class="px-2 py-0.5 rounded-full bg-emerald-950/60 text-emerald-300 border border-emerald-800/40 text-[11px] font-bold">
                                {{ $donePostIts->count() }}
                            </span>
                        </div>
                    </div>

                    {{-- Collapsed Indicator --}}
                    <div x-show="!openDone" class="flex flex-col items-center py-6 gap-3 cursor-pointer" @click="openDone = true">
                        <span class="[writing-mode:vertical-rl] text-xs font-bold text-emerald-400 tracking-wider uppercase rotate-180">
                            Completed ({{ $donePostIts->count() }})
                        </span>
                    </div>

                    {{-- Expanded Card List Drop Zone --}}
                    <div
                        x-show="openDone"
                        @dragover.prevent="onDragOver('done')"
                        @dragleave="onDragLeave('done')"
                        @drop.prevent="onDrop(null, 'done')"
                        :class="activeDropZone === 'done' ? 'border-emerald-500 bg-emerald-950/20' : 'border-transparent'"
                        class="space-y-3 min-h-[300px] rounded-xl border-2 border-dashed p-1 transition-all"
                    >
                        @forelse ($donePostIts as $postIt)
                            @include('livewire.partials.post-it-card', ['postIt' => $postIt, 'zone' => 'done'])
                        @empty
                            <div class="py-12 px-4 text-center border border-dashed border-slate-800 rounded-xl text-slate-500 text-xs">
                                No completed tasks yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </main>

    {{-- MODAL 1: Create Post-It Modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg shadow-2xl p-6 sm:p-8 text-left">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div class="flex items-center gap-2.5">
                        <div class="h-8 w-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold text-sm">
                            担
                        </div>
                        <h3 class="text-lg font-bold text-white">Create New Post-it</h3>
                    </div>
                    <button
                        wire:click="$set('showCreateModal', false)"
                        class="text-slate-400 hover:text-white transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form wire:submit="createPostIt" class="mt-5 space-y-4">
                    {{-- Title --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Title</label>
                        <input
                            wire:model="title"
                            type="text"
                            required
                            placeholder="e.g. Integrate Stripe Webhook Handlers"
                            class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                        >
                        @error('title') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Description (optional)</label>
                        <textarea
                            wire:model="description"
                            rows="2"
                            placeholder="Provide concise context or acceptance criteria..."
                            class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                        ></textarea>
                    </div>

                    {{-- Department & Priority Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Department</label>
                            <select
                                wire:model="departmentId"
                                class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="">Select Department...</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Priority</label>
                            <select
                                wire:model.live="priority"
                                class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="low">Low (0.8x)</option>
                                <option value="normal">Normal (1.0x)</option>
                                <option value="high">High (1.25x)</option>
                                <option value="urgent">Urgent (1.5x)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Weight (1 to 5 Cognitive Load) --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Cognitive Load / Weight (1–5)</label>
                            <span class="text-xs text-indigo-400 font-semibold">Weight {{ $weight }}</span>
                        </div>
                        <div class="grid grid-cols-5 gap-2">
                            @for ($w = 1; $w <= 5; $w++)
                                <button
                                    type="button"
                                    wire:click="$set('weight', {{ $w }})"
                                    class="py-2 rounded-xl text-xs font-bold border transition-all cursor-pointer {{ $weight === $w ? 'bg-indigo-600 border-indigo-400 text-white shadow-md shadow-indigo-600/30' : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800' }}"
                                >
                                    {{ $w }}
                                </button>
                            @endfor
                        </div>
                    </div>

                    {{-- Direct Collaborator Selection (Assignee) --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Direct Collaborator Picker (Tantou)
                        </label>
                        <select
                            wire:model="assigneeId"
                            class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-3.5 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">None — Send to Unassigned Backlog</option>
                            @foreach ($availableCollaborators as $collab)
                                @php
                                    $collabStatus = $collab->current_capacity >= $collab->max_capacity
                                        ? 'High Load'
                                        : ($collab->current_capacity >= ($collab->max_capacity * 0.7) ? 'Busy' : 'Healthy');
                                @endphp
                                <option value="{{ $collab->id }}">
                                    {{ $collab->name }} — {{ $collab->current_capacity }}/{{ $collab->max_capacity }} ({{ $collabStatus }}) [{{ $collab->department?->name ?? 'No Dept' }}]
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Selecting a collaborator moves the task directly to their active Doing column.
                        </p>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-3">
                        <button
                            wire:click="$set('showCreateModal', false)"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white transition-colors cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/25 transition-all cursor-pointer"
                        >
                            Create Post-it
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
                        <h3 class="text-base font-bold text-white">Co-op / Assist Assignment</h3>
                    </div>
                    <button
                        wire:click="$set('showCoOpModal', false)"
                        class="text-slate-400 hover:text-white transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Select Co-op Helper</label>
                        <select
                            wire:model="coOpHelperId"
                            class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-3.5 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">No Co-op Helper (Solo Execution)</option>
                            @foreach ($availableCollaborators as $helperOption)
                                <option value="{{ $helperOption->id }}">
                                    {{ $helperOption->name }} ({{ $helperOption->current_capacity }}/{{ $helperOption->max_capacity }} cap)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($coOpHelperId)
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Helper Split Percentage (0–50%)</label>
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
                                <span>50% (Max)</span>
                            </div>

                            {{-- Live split preview --}}
                            <div class="mt-3 p-3 rounded-xl bg-slate-950/80 border border-purple-500/30 flex items-center justify-between text-xs">
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Tantou Assignee</span>
                                    <span class="font-bold text-white">{{ 100 - $coOpHelperSplit }}% of points</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-purple-300 block text-[10px]">Co-op Helper</span>
                                    <span class="font-bold text-purple-400">{{ $coOpHelperSplit }}% of points</span>
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
                            Cancel
                        </button>
                        <button
                            wire:click="saveCoOpSplit"
                            type="button"
                            class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold shadow-lg shadow-purple-600/30 transition-all cursor-pointer"
                        >
                            Save Co-op Split
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
