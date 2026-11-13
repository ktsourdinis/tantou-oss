@php
    $weightMinHeight = match($postIt->weight) {
        1 => 'min-h-[52px]',
        2 => 'min-h-[68px]',
        3 => 'min-h-[84px]',
        4 => 'min-h-[104px]',
        default => 'min-h-[128px]',
    };

    $priorityBorder = match($postIt->priority) {
        'urgent' => 'border-l-rose-500',
        'high' => 'border-l-amber-500',
        'low' => 'border-l-slate-500',
        default => 'border-l-sky-500',
    };

    $priorityBadge = match($postIt->priority) {
        'urgent' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
        'high' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
        'low' => 'bg-slate-500/20 text-slate-400 border-slate-500/30',
        default => 'bg-sky-500/20 text-sky-300 border-sky-500/30',
    };

    $isDone = $zone === 'done';
    $isDoing = $zone === 'doing';
    $currentUser = auth()->user();
    $canManageCoop = $isDoing && $currentUser && ($currentUser->role === 'admin' || (int) $currentUser->id === (int) $postIt->assignee_id);
@endphp

<div
    draggable="{{ $isDone ? 'false' : 'true' }}"
    @dragstart="onDragStart($event, {{ $postIt->id }})"
    @dragend="onDragEnd()"
    class="group relative rounded-xl border border-slate-800 bg-slate-900/90 border-l-4 {{ $priorityBorder }} {{ $weightMinHeight }} p-3 shadow-md hover:shadow-xl hover:border-slate-700 transition-all {{ $isDone ? 'opacity-85' : 'cursor-grab active:cursor-grabbing hover:-translate-y-0.5' }}"
>
    {{-- Card Header: Priority & Department & Bounty Points --}}
    <div class="flex items-center justify-between gap-1.5 mb-1.5">
        <div class="flex items-center gap-1.5 flex-wrap">
            {{-- Priority Pill --}}
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $priorityBadge }}">
                {{ $postIt->priority }}
            </span>

            {{-- Weight Tag --}}
            <span class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 text-[10px] font-semibold" title="Weight (Cognitive Load)">
                W{{ $postIt->weight }}
            </span>

            {{-- Department Pill (if available) --}}
            @if ($postIt->department)
                <span
                    class="px-1.5 py-0.5 rounded text-[10px] font-semibold border truncate max-w-[85px]"
                    style="background-color: {{ $postIt->department->color }}15; color: {{ $postIt->department->color }}; border-color: {{ $postIt->department->color }}30;"
                >
                    {{ $postIt->department->name }}
                </span>
            @endif

            {{-- User's Own Request Highlight Badge --}}
            @if ($postIt->is_own)
                <span class="px-1.5 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 text-[10px] font-bold border border-indigo-500/40 animate-pulse">
                    Δικό μου
                </span>
            @endif
        </div>

        {{-- Calculated Bounty Points Badge --}}
        <div class="shrink-0 flex items-center gap-1 font-bold text-xs {{ $isDone ? 'text-emerald-400' : 'text-amber-400' }}" title="Calculated Bounty Points">
            <span>⚡</span>
            <span>{{ $postIt->bounty_points }} pts</span>
        </div>
    </div>

    {{-- Task Title --}}
    <h4 class="text-xs font-semibold text-slate-100 leading-snug group-hover:text-white transition-colors {{ $isDone ? 'line-through text-slate-400' : '' }}">
        {{ $postIt->title }}
    </h4>

    {{-- Task Description (Hidden in Compact mode or if Masked by Privacy Matrix) --}}
    @if (! $isCompact && $postIt->description && ! $postIt->is_masked)
        <p class="text-[11px] text-slate-400 mt-1 line-clamp-2 leading-relaxed">
            {{ $postIt->description }}
        </p>
    @elseif ($postIt->is_masked)
        <p class="text-[10px] text-slate-600 italic mt-1">
            [Προστατευμένη περιγραφή]
        </p>
    @endif

    {{-- Co-Op Assist Information Badge --}}
    @if ($postIt->helper_id && $postIt->helper)
        <div class="mt-2 flex items-center justify-between px-2 py-1 rounded-lg bg-purple-950/40 border border-purple-800/40 text-[10px] text-purple-300">
            <span class="flex items-center gap-1 font-medium">
                <span>🤝</span>
                <span>Co-op: {{ $postIt->helper->name }}</span>
            </span>
            <span class="font-bold text-purple-400">
                {{ $postIt->helper_split }}% split
            </span>
        </div>
    @endif

    {{-- Card Footer --}}
    <div class="mt-2.5 pt-2 border-t border-slate-800/80 flex items-center justify-between text-[10px] text-slate-400">
        {{-- Requester Info (anonymized for non-admins on other cards) --}}
        <div class="flex items-center gap-1.5 truncate max-w-[140px]">
            <span class="text-slate-500">Aπό:</span>
            <span class="truncate font-medium {{ $postIt->is_own ? 'text-indigo-300 font-semibold' : 'text-slate-300' }}">
                {{ $postIt->display_requester_name }}
            </span>
        </div>

        {{-- Actions: Co-Op Button (for doing tasks) / Timestamp --}}
        <div class="flex items-center gap-1.5 shrink-0">
            @if ($canManageCoop)
                <button
                    wire:click="openCoOpModal({{ $postIt->id }})"
                    type="button"
                    class="px-1.5 py-0.5 rounded bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 hover:text-purple-200 border border-purple-500/30 transition-all cursor-pointer font-medium text-[10px]"
                    title="Assign Co-op Assistant"
                >
                    🤝 Co-op
                </button>
            @endif

            @if ($isDone && $postIt->completed_at)
                <span class="text-emerald-400 font-medium">✓ {{ $postIt->completed_at->diffForHumans(null, true) }}</span>
            @elseif ($isDoing && $postIt->started_at)
                <span class="text-slate-500">{{ $postIt->started_at->diffForHumans(null, true) }}</span>
            @endif
        </div>
    </div>
</div>
