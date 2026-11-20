@php
    $weightMinHeight = $isCompact
        ? match($postIt->weight) {
            1 => 'min-h-[38px]',
            2 => 'min-h-[44px]',
            3 => 'min-h-[52px]',
            4 => 'min-h-[60px]',
            default => 'min-h-[68px]',
        }
        : match($postIt->weight) {
            1 => 'min-h-[44px]',
            2 => 'min-h-[54px]',
            3 => 'min-h-[66px]',
            4 => 'min-h-[78px]',
            default => 'min-h-[92px]',
        };

    $cardPadding = $isCompact ? 'p-2' : 'p-2.5';

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
        ($isClient && ($isRequester || ($currentUser && filled($currentUser->facility_name) && $postIt->facility_name === $currentUser->facility_name)) && in_array($postIt->status, ['backlog', 'assigned'], true) && $postIt->started_at === null)
    );

    $isLockedForClient = $isClient && $isRequester && $isDoing && $postIt->started_at !== null;

    $assigneeVisibility = $postIt->assignee?->board_visibility ?? ($worker->board_visibility ?? 'public');
    $canManageCoop = ($isDoing || $isAssigned) && $currentUser && ($isAdmin || $isAssignee || ($isCoordinator && (int) $currentUser->department_id === (int) $postIt->assignee?->department_id)) && $assigneeVisibility !== 'private';
    $isClientRequester = $postIt->requester && $postIt->requester->role === 'client';
@endphp

@if (!empty($postIt->is_foreign_slot))
    <div
        class="rounded-xl border border-slate-800/80 bg-slate-950/70 {{ $weightMinHeight }} p-2.5 shadow-sm flex items-center justify-between text-slate-500 cursor-not-allowed select-none transition-all"
        title="{{ __('Occupied Slot (Confidential request from another organization)') }}"
    >
        <div class="flex items-center gap-2 text-xs font-medium text-slate-400">
            <span>🔒</span>
            <span class="font-semibold text-slate-300">{{ __('Occupied Slot') }}</span>
            <span class="text-slate-600">•</span>
            <span class="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 text-[10px] font-semibold text-slate-400">
                W{{ $postIt->weight }}
            </span>
        </div>
        <div class="shrink-0 font-bold text-xs text-slate-500 font-mono">
            ⚡ {{ $postIt->bounty_points }} pts
        </div>
    </div>
@else
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
    class="group relative rounded-xl border border-slate-800 bg-slate-900/90 border-l-4 {{ $priorityBorder }} {{ $weightMinHeight }} {{ $cardPadding }} shadow-md hover:shadow-xl hover:border-slate-700 transition-all min-w-0 max-w-full overflow-hidden {{ $isDone ? 'opacity-85' : '' }} {{ $canDrag ? 'cursor-grab active:cursor-grabbing hover:-translate-y-0.5' : ($canInspect ? 'cursor-pointer hover:-translate-y-0.5' : 'cursor-default') }}"
    @if (! $canInspect)
        title="{{ __('Protected request (accessible only to requester and assignee team)') }}"
    @endif
>
    {{-- Card Header: Priority & Workload & Status & Bounty Points --}}
    <div class="flex items-center justify-between gap-1 mb-1 min-w-0">
        <div class="flex items-center gap-1 flex-wrap min-w-0">
            {{-- Priority Pill --}}
            <span class="px-1.5 py-[1px] rounded text-[9.5px] leading-tight font-bold uppercase tracking-wider border {{ $priorityBadge }}">
                {{ __(ucfirst($postIt->priority)) }}
            </span>

            {{-- Weight Tag --}}
            <span class="px-1.5 py-[1px] rounded bg-slate-800 text-slate-300 text-[9.5px] leading-tight font-semibold" title="{{ __('Weight (Cognitive Load)') }}">
                W{{ $postIt->weight }}
            </span>

            {{-- Two-Stage Status Indicator (Assigned vs Doing) --}}
            @if ($isAssigned)
                <span class="px-1.5 py-[1px] rounded text-[9.5px] leading-tight font-semibold bg-sky-500/15 text-sky-300 border border-sky-500/30" title="{{ __('Assigned but not yet started') }}">
                    {{ __('Assigned') }}
                </span>
            @elseif ($isDoing)
                <span class="px-1.5 py-[1px] rounded text-[9.5px] leading-tight font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30" title="{{ __('Actively in progress') }}">
                    {{ __('Doing') }}
                </span>
            @endif

            {{-- Department Pill (tucked on small screens inside worker columns & compact mode) --}}
            @if ($postIt->department)
                <span
                    class="{{ ($zone ?? null) === 'doing' ? 'hidden sm:inline-block' : ($isCompact ? 'hidden sm:inline-block' : 'inline-block') }} px-1.5 py-[1px] rounded text-[9.5px] leading-tight font-semibold border truncate max-w-[78px]"
                    style="background-color: {{ $postIt->department->color }}15; color: {{ $postIt->department->color }}; border-color: {{ $postIt->department->color }}30;"
                >
                    {{ $postIt->department->name }}
                </span>
            @endif

            {{-- User's Own Request Highlight Badge --}}
            @if ($postIt->is_own)
                <span class="px-1.5 py-[1px] rounded-full bg-indigo-500/20 text-indigo-300 text-[9.5px] leading-tight font-bold border border-indigo-500/40">
                    {{ __('Mine') }}
                </span>
            @endif

            {{-- In Progress Lock for Client --}}
            @if ($isLockedForClient)
                <span class="px-1.5 py-[1px] rounded-full bg-amber-500/20 text-amber-300 text-[9.5px] leading-tight font-bold border border-amber-500/40" title="{{ __('Card is actively being worked on and locked from client reassignment') }}">
                    🔒 {{ __('In Progress') }}
                </span>
            @endif

            {{-- Canceled vs Closed by Requester Badge --}}
            @if ($isDone && $postIt->is_canceled)
                <span class="px-1.5 py-[1px] rounded-full bg-rose-500/20 text-rose-300 text-[9.5px] leading-tight font-bold border border-rose-500/30 tracking-wider" title="{{ __('Canceled') }}">
                    ✖ {{ __('CANCELED') }}
                </span>
            @elseif ($isDone && $postIt->isClosedByRequester())
                <span class="px-1.5 py-[1px] rounded-full bg-teal-500/20 text-teal-300 text-[9.5px] leading-tight font-semibold border border-teal-500/40" title="{{ __('Closed by Requester') }}">
                    ✓ {{ __('Closed by Requester') }}
                </span>
            @endif
        </div>

        {{-- Calculated Bounty Points Badge --}}
        @if ($isDone && $postIt->is_canceled)
            <div class="shrink-0 flex items-center gap-0.5 font-bold text-[11px] text-slate-500 line-through" title="{{ __('Canceled (0 points awarded)') }}">
                <span>⚡</span>
                <span>0 pts</span>
            </div>
        @else
            <div class="shrink-0 flex items-center gap-0.5 font-bold text-[11px] {{ $isDone ? 'text-emerald-400' : 'text-amber-400' }}" title="{{ __('Calculated Bounty Points') }}">
                <span>⚡</span>
                <span>{{ $postIt->bounty_points }} pts</span>
            </div>
        @endif
    </div>

    {{-- Task Title --}}
    <h4 class="text-xs font-semibold text-slate-100 leading-snug group-hover:text-white transition-colors break-words min-w-0 {{ $isCompact ? 'line-clamp-2' : '' }} {{ $isDone ? 'line-through text-slate-400' : '' }}">
        {{ $postIt->title }}
    </h4>

    {{-- Task Description (Hidden in Compact mode or if Masked by Privacy Matrix) --}}
    @if (! $isCompact && $postIt->description && ! $postIt->is_masked)
        <p class="text-[11px] text-slate-400 mt-0.5 line-clamp-2 leading-snug">
            {{ $postIt->description }}
        </p>
    @elseif (! $isCompact && $postIt->is_masked)
        <p class="text-[10px] text-slate-600 italic mt-0.5">
            [{{ __('Protected description') }}]
        </p>
    @endif

    {{-- Visual Flow Chain Badge (A -> B -> C -> ...) --}}
    @php
        $assignmentChain = $postIt->getAssignmentChain();
    @endphp
    @if (count($assignmentChain) >= 2)
        <div class="mt-1.5 flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-indigo-950/40 border border-indigo-500/20 text-[9.5px] text-indigo-300 font-medium truncate max-w-full" title="{{ __('Assignment Flow Trail:') }} {{ implode(' → ', $assignmentChain) }}{{ $isDone ? ($postIt->is_canceled ? ' → ✖' : ' → ✓') : '' }}">
            <span class="shrink-0 text-indigo-400">🔀</span>
            <span class="truncate">
                {{ implode(' → ', $assignmentChain) }}
                @if ($isDone)
                    @if ($postIt->is_canceled)
                        <span class="text-rose-400 font-bold ml-0.5">✖</span>
                    @else
                        <span class="text-emerald-400 font-bold ml-0.5">✓</span>
                    @endif
                @endif
            </span>
        </div>
    @endif

    {{-- Co-Op Assist Information Badge --}}
    @if ($postIt->helper_id && $postIt->helper)
        <div class="mt-1.5 flex items-center justify-between px-1.5 py-0.5 rounded-md bg-purple-950/40 border border-purple-800/40 text-[9.5px] text-purple-300">
            <span class="flex items-center gap-1 font-medium truncate">
                <span>🤝</span>
                <span class="truncate">{{ __('Co-op:') }} {{ $postIt->helper->name }}</span>
            </span>
            <span class="font-bold text-purple-400 shrink-0 ml-1">
                {{ $postIt->helper_split }}% {{ __('split') }}
            </span>
        </div>
    @endif

    {{-- Card Footer --}}
    <div class="{{ $isCompact ? 'mt-1.5 pt-1' : 'mt-2 pt-1.5' }} border-t border-slate-800/80 space-y-0.5">
        <div class="flex items-center justify-between gap-1.5 text-[10px] text-slate-400">
            {{-- Requester Info (open for internal staff, anonymized for non-admins on other cards) --}}
            <div class="flex items-center gap-1 truncate min-w-0">
                <span class="text-slate-500 hidden sm:inline">{{ __('From:') }}</span>
                <span class="truncate font-medium {{ $postIt->is_own ? 'text-indigo-300 font-semibold' : 'text-slate-300' }}">
                    {{ $postIt->display_requester_name }}
                </span>
                @if (!empty($postIt->facility_name) && !$postIt->is_masked)
                    <span class="px-1 py-[1px] rounded bg-sky-500/10 text-sky-400 border border-sky-500/20 text-[9px] leading-tight font-medium shrink-0 max-w-[92px] truncate" title="{{ __('Facility / Unit') }}: {{ $postIt->facility_name }}">
                        🏢 {{ $postIt->facility_name }}
                    </span>
                @endif
                @if (!empty($postIt->guest_name) && !$postIt->is_masked)
                    <span class="px-1 py-[1px] rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[9px] leading-tight font-medium shrink-0" title="{{ __('Guest Requester') }}{{ !empty($postIt->guest_contact) ? ': ' . $postIt->guest_contact : '' }}">
                        📥
                    </span>
                @endif
            </div>

            {{-- Actions: Start Button / Co-Op / Comments / Timestamp --}}
            <div class="flex items-center gap-1 shrink-0">
                @if ($canStart)
                    <button
                        wire:click.stop="startPostIt({{ $postIt->id }})"
                        type="button"
                        class="px-1.5 py-0.5 rounded-md bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[10px] shadow-sm flex items-center gap-0.5 transition-all cursor-pointer"
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
                        class="px-1.5 py-0.5 rounded bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 hover:text-purple-200 border border-purple-500/30 transition-all cursor-pointer font-medium text-[9.5px]"
                        title="{{ __('Assign Co-op Assistant') }}"
                    >
                        🤝 <span class="hidden sm:inline">{{ __('Co-op') }}</span>
                    </button>
                @endif

                @if (($postIt->comments_count ?? 0) > 0)
                    <span class="inline-flex items-center gap-0.5 px-1 py-[1px] rounded bg-slate-800/90 text-slate-300 text-[9.5px] border border-slate-700/60" title="{{ $postIt->comments_count }} {{ __('comments') }}">
                        <span>💬</span>
                        <span class="font-semibold">{{ $postIt->comments_count }}</span>
                    </span>
                @endif

                @if (($postIt->attachments_count ?? 0) > 0)
                    <span class="inline-flex items-center gap-0.5 px-1 py-[1px] rounded bg-slate-800/90 text-slate-300 text-[9.5px] border border-slate-700/60" title="{{ $postIt->attachments_count }} {{ __('attachments') }}">
                        <span>📎</span>
                        <span class="font-semibold">{{ $postIt->attachments_count }}</span>
                    </span>
                @endif

                @if ($isDone && $postIt->completed_at)
                    @php
                        $cardViewerTz = auth()->user()?->effectiveTimezone() ?? 'Europe/Athens';
                        $cardCompletedLocal = $postIt->completed_at->timezone($cardViewerTz);
                        $cardTzBadge = $cardCompletedLocal->format('T');
                        if (str_starts_with($cardTzBadge, '+') || str_starts_with($cardTzBadge, '-')) {
                            $cardTzBadge = 'UTC'.$cardTzBadge;
                        }
                    @endphp
                    @if ($postIt->is_canceled)
                        <span class="text-rose-400 font-medium text-[9.5px]" title="{{ $cardCompletedLocal->format('d/m/Y H:i') }} ({{ $cardTzBadge }})">✖ {{ __('Canceled') }} {{ $postIt->completed_at->diffForHumans(null, true) }}</span>
                    @else
                        <span class="text-emerald-400 font-medium text-[9.5px]" title="{{ $cardCompletedLocal->format('d/m/Y H:i') }} ({{ $cardTzBadge }})">✓ {{ $postIt->completed_at->diffForHumans(null, true) }}</span>
                    @endif
                @elseif ($isDoing && $postIt->started_at)
                    @php
                        $cardViewerTz = auth()->user()?->effectiveTimezone() ?? 'Europe/Athens';
                        $cardStartedLocal = $postIt->started_at->timezone($cardViewerTz);
                        $cardTzBadge = $cardStartedLocal->format('T');
                        if (str_starts_with($cardTzBadge, '+') || str_starts_with($cardTzBadge, '-')) {
                            $cardTzBadge = 'UTC'.$cardTzBadge;
                        }
                    @endphp
                    <span class="text-slate-500 text-[9.5px]" title="{{ $cardStartedLocal->format('d/m/Y H:i') }} ({{ $cardTzBadge }})">{{ $postIt->started_at->diffForHumans(null, true) }}</span>
                @endif
            </div>
        </div>

        {{-- Forwarded / Assigned By attribution (tucked cleanly in compact mobile view) --}}
        @if (!empty($postIt->display_assigned_by_name) && (int) $postIt->assigned_by_id !== (int) $postIt->requester_id)
            <div class="{{ $isCompact ? 'hidden sm:flex' : 'flex' }} text-[9px] leading-tight text-slate-400 truncate items-center gap-1" title="{{ __('Assigned by:') }} {{ $postIt->display_assigned_by_name }}">
                <span class="text-slate-500">{{ __('Assigned by:') }}</span>
                <span class="text-indigo-300 font-medium truncate">{{ $postIt->display_assigned_by_name }}</span>
            </div>
        @endif
    </div>
</div>
@endif

