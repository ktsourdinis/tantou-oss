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

    $isDone = $postIt->status === 'done';
    $isDoing = $postIt->status === 'doing';
    $isAssigned = $postIt->status === 'assigned';
    $isBacklog = $postIt->status === 'backlog';

    $currentUser = auth()->user();
    $isAdmin = $currentUser && $currentUser->role === 'admin';
    $isCoordinator = $currentUser && $currentUser->role === 'coordinator';
    $isWorker = $currentUser && $currentUser->role === 'worker';
    $isClient = $currentUser && $currentUser->role === 'client';
    $isRequester = $currentUser && (int) $postIt->requester_id === (int) $currentUser->id;
    $isAssignee = $currentUser && (int) $postIt->assignee_id === (int) $currentUser->id;
    $isHelper = $currentUser && (int) $postIt->helper_id === (int) $currentUser->id;

    // Authorized to inspect (open slide-over):
    // admin, coordinator in department, requester, assignee, helper, or worker inspecting backlog
    $canInspect = $isAdmin
        || $isRequester
        || $isAssignee
        || $isHelper
        || ($isCoordinator && ($isBacklog || (int) $postIt->department_id === (int) $currentUser->department_id || (int) $postIt->assignee?->department_id === (int) $currentUser->department_id))
        || ($isWorker && $isBacklog);

    // Can start: assigned card and user is assignee, coordinator of department, or admin
    $canStart = $isAssigned && (
        $isAdmin ||
        $isAssignee ||
        ($isCoordinator && ((int) $currentUser->department_id === (int) $postIt->department_id || (int) $currentUser->department_id === (int) $postIt->assignee?->department_id))
    );

    // Drag-and-drop guardrail:
    // - Completed cards cannot be dragged
    // - admin: can drag any active card
    // - coordinator: can move/rebalance cards across collaborators within their department
    // - worker: can pull from backlog, move cards where assignee/helper
    // - client: CAN drag own Post-it between available public collaborators ONLY while 'assigned' or 'backlog' (started_at === null)
    $canDrag = ! $isDone && (
        $isAdmin ||
        ($isCoordinator && ($isBacklog || (int) $postIt->department_id === (int) $currentUser->department_id || (int) $postIt->assignee?->department_id === (int) $currentUser->department_id || $isAssignee || $isHelper)) ||
        ($isWorker && ($isBacklog || $isAssignee || $isHelper)) ||
        ($isClient && $isRequester && in_array($postIt->status, ['backlog', 'assigned'], true) && $postIt->started_at === null)
    );

    $isLockedForClient = $isClient && $isRequester && $isDoing && $postIt->started_at !== null;

    $assigneeVisibility = $postIt->assignee?->board_visibility ?? ($worker->board_visibility ?? 'public');
    $canManageCoop = ($isDoing || $isAssigned) && $currentUser && ($isAdmin || $isAssignee || ($isCoordinator && (int) $currentUser->department_id === (int) $postIt->assignee?->department_id)) && $assigneeVisibility !== 'private';
    $isClientRequester = $postIt->requester && $postIt->requester->role === 'client';
@endphp

<div
    data-post-it-id="{{ $postIt->id }}"
    draggable="{{ $canDrag ? 'true' : 'false' }}"
    @if ($canDrag)
        @dragstart="onDragStart($event, {{ $postIt->id }}, {{ $isClientRequester ? 'true' : 'false' }})"
        @dragend="onDragEnd()"
    @endif
    @if ($canInspect)
        @click="if (!didJustDrag && !$event.target.closest('button, select, input, a')) { $wire.openSlideOver({{ $postIt->id }}) }"
    @endif
    class="group relative rounded-xl border border-slate-800 bg-slate-900/90 border-l-4 {{ $priorityBorder }} {{ $weightMinHeight }} p-3 shadow-md hover:shadow-xl hover:border-slate-700 transition-all {{ $isDone ? 'opacity-85' : '' }} {{ $canDrag ? 'cursor-grab active:cursor-grabbing hover:-translate-y-0.5' : ($canInspect ? 'cursor-pointer hover:-translate-y-0.5' : 'cursor-default') }}"
    @if (! $canInspect)
        title="{{ __('Protected request (accessible only to requester and assignee team)') }}"
    @endif
>
    {{-- Card Header: Priority & Department & Bounty Points --}}
    <div class="flex items-center justify-between gap-1.5 mb-1.5">
        <div class="flex items-center gap-1.5 flex-wrap">
            {{-- Priority Pill --}}
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $priorityBadge }}">
                {{ __($postIt->priority) }}
            </span>

            {{-- Weight Tag --}}
            <span class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 text-[10px] font-semibold" title="{{ __('Weight (Cognitive Load)') }}">
                W{{ $postIt->weight }}
            </span>

            {{-- Two-Stage Status Indicator (Assigned vs Doing) --}}
            @if ($isAssigned)
                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-sky-500/15 text-sky-300 border border-sky-500/30" title="{{ __('Assigned but not yet started') }}">
                    {{ __('Assigned') }}
                </span>
            @elseif ($isDoing)
                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30" title="{{ __('Actively in progress') }}">
                    {{ __('Doing') }}
                </span>
            @endif

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
                    {{ __('Mine') }}
                </span>
            @endif

            {{-- In Progress Lock for Client --}}
            @if ($isLockedForClient)
                <span class="px-1.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 text-[10px] font-bold border border-amber-500/40" title="{{ __('Card is actively being worked on and locked from client reassignment') }}">
                    🔒 {{ __('In Progress') }}
                </span>
            @endif
        </div>

        {{-- Calculated Bounty Points Badge --}}
        <div class="shrink-0 flex items-center gap-1 font-bold text-xs {{ $isDone ? 'text-emerald-400' : 'text-amber-400' }}" title="{{ __('Calculated Bounty Points') }}">
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
            [{{ __('Protected description') }}]
        </p>
    @endif

    {{-- Visual Flow Chain Badge (A -> B -> C -> ...) --}}
    @php
        $assignmentChain = $postIt->getAssignmentChain();
    @endphp
    @if (count($assignmentChain) >= 2)
        <div class="mt-2 flex items-center gap-1.5 px-2 py-1 rounded-lg bg-indigo-950/40 border border-indigo-500/20 text-[10px] text-indigo-300 font-medium truncate max-w-full" title="Assignment Flow Trail: {{ implode(' → ', $assignmentChain) }}{{ $isDone ? ' → ✓' : '' }}">
            <span class="shrink-0 text-indigo-400">🔀</span>
            <span class="truncate">
                {{ implode(' → ', $assignmentChain) }}
                @if ($isDone)
                    <span class="text-emerald-400 font-bold ml-0.5">✓</span>
                @endif
            </span>
        </div>
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
    <div class="mt-2.5 pt-2 border-t border-slate-800/80 space-y-1">
        <div class="flex items-center justify-between text-[10px] text-slate-400">
            {{-- Requester Info (open for internal staff, anonymized for non-admins on other cards) --}}
            <div class="flex items-center gap-1.5 truncate max-w-[130px]">
                <span class="text-slate-500">{{ __('From:') }}</span>
                <span class="truncate font-medium {{ $postIt->is_own ? 'text-indigo-300 font-semibold' : 'text-slate-300' }}">
                    {{ $postIt->display_requester_name }}
                </span>
            </div>

            {{-- Actions: Start Button / Co-Op / Comments / Timestamp --}}
            <div class="flex items-center gap-1.5 shrink-0">
                @if ($canStart)
                    <button
                        wire:click.stop="startPostIt({{ $postIt->id }})"
                        type="button"
                        class="px-2 py-0.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[10px] shadow-sm flex items-center gap-1 transition-all cursor-pointer"
                        title="{{ __('Start working on this task (marks as Doing)') }}"
                    >
                        <span>▶</span>
                        <span>{{ __('Start') }}</span>
                    </button>
                @endif

                @if ($canManageCoop)
                    <button
                        wire:click.stop="openCoOpModal({{ $postIt->id }})"
                        type="button"
                        class="px-1.5 py-0.5 rounded bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 hover:text-purple-200 border border-purple-500/30 transition-all cursor-pointer font-medium text-[10px]"
                        title="{{ __('Assign Co-op Assistant') }}"
                    >
                        🤝 {{ __('Co-op') }}
                    </button>
                @endif

                @if (($postIt->comments_count ?? 0) > 0)
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-800/90 text-slate-300 text-[10px] border border-slate-700/60" title="{{ $postIt->comments_count }} {{ __('comments') }}">
                        <span>💬</span>
                        <span class="font-semibold">{{ $postIt->comments_count }}</span>
                    </span>
                @endif

                @if (($postIt->attachments_count ?? 0) > 0)
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-800/90 text-slate-300 text-[10px] border border-slate-700/60" title="{{ $postIt->attachments_count }} {{ __('attachments') }}">
                        <span>📎</span>
                        <span class="font-semibold">{{ $postIt->attachments_count }}</span>
                    </span>
                @endif

                @if ($isDone && $postIt->completed_at)
                    <span class="text-emerald-400 font-medium">✓ {{ $postIt->completed_at->diffForHumans(null, true) }}</span>
                @elseif ($isDoing && $postIt->started_at)
                    <span class="text-slate-500">{{ $postIt->started_at->diffForHumans(null, true) }}</span>
                @endif
            </div>
        </div>

        {{-- Forwarded / Assigned By attribution --}}
        @if (!empty($postIt->display_assigned_by_name) && (int) $postIt->assigned_by_id !== (int) $postIt->requester_id)
            <div class="text-[9px] text-slate-400 truncate flex items-center gap-1">
                <span class="text-slate-500">{{ __('Assigned by:') }}</span>
                <span class="text-indigo-300 font-medium truncate">{{ $postIt->display_assigned_by_name }}</span>
            </div>
        @endif
    </div>
</div>

