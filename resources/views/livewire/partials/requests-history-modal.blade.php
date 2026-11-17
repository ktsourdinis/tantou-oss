@if ($showRequestsHistoryModal)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-slate-950/80 backdrop-blur-md transition-all animate-fadeIn"
        x-data
        @keydown.escape.window="$wire.closeRequestsHistoryModal()"
    >
        <div
            @click.away="$wire.closeRequestsHistoryModal()"
            class="bg-slate-900 border border-slate-800 rounded-3xl max-w-5xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
        >
            {{-- Modal Header --}}
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 shrink-0">
                <div class="flex items-center gap-2.5">
                    <span class="text-xl">📋</span>
                    <div>
                        <h3 class="text-base font-bold text-white">{{ __('Requests History') }}</h3>
                        <p class="text-xs text-slate-400">
                            @if (auth()->user()?->role === 'client')
                                {{ __('All requests submitted by you or your facility.') }}
                            @else
                                {{ __('Organization-wide audit trail of active and completed requests.') }}
                            @endif
                        </p>
                    </div>
                </div>
                <button
                    wire:click="closeRequestsHistoryModal"
                    type="button"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer text-xs"
                    title="{{ __('Close') }}"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Controls: Search and Status Filters --}}
            <div class="px-6 py-3 border-b border-slate-800/80 bg-slate-950/40 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
                {{-- Search Input --}}
                <div class="relative w-full sm:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <input
                        wire:model.live.debounce.250ms="historySearch"
                        type="text"
                        placeholder="{{ __('Search requests or facility...') }}"
                        class="w-full pl-9 pr-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20"
                    >
                </div>

                {{-- Status Filter Pills --}}
                <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-900 border border-slate-800 text-xs w-full sm:w-auto justify-center sm:justify-start">
                    <button
                        wire:click="$set('historyStatusFilter', 'all')"
                        type="button"
                        class="px-3 py-1.5 rounded-lg font-semibold transition-all cursor-pointer {{ $historyStatusFilter === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                    >
                        {{ __('All') }}
                    </button>
                    <button
                        wire:click="$set('historyStatusFilter', 'active')"
                        type="button"
                        class="px-3 py-1.5 rounded-lg font-semibold transition-all cursor-pointer {{ $historyStatusFilter === 'active' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                    >
                        {{ __('Active') }}
                    </button>
                    <button
                        wire:click="$set('historyStatusFilter', 'completed')"
                        type="button"
                        class="px-3 py-1.5 rounded-lg font-semibold transition-all cursor-pointer {{ $historyStatusFilter === 'completed' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                    >
                        {{ __('Completed') }}
                    </button>
                </div>
            </div>

            {{-- Table of Requests --}}
            <div class="overflow-y-auto flex-1 p-4 sm:p-6">
                @php
                    $requests = $this->historyRequests;
                @endphp

                @if ($requests->isEmpty())
                    <div class="py-16 text-center">
                        <div class="text-3xl mb-2">🔍</div>
                        <p class="text-sm font-semibold text-slate-300">{{ __('No requests found.') }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ __('Try adjusting your search or filters.') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-slate-800">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-950/80 border-b border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                                    <th class="py-3 px-4">{{ __('Request / Title') }}</th>
                                    <th class="py-3 px-4">{{ __('Status') }}</th>
                                    <th class="py-3 px-4">{{ __('Requester / Facility') }}</th>
                                    <th class="py-3 px-4">{{ __('Assigned To') }}</th>
                                    <th class="py-3 px-4">{{ __('Created') }}</th>
                                    <th class="py-3 px-4">{{ __('Completed Date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 text-xs">
                                @foreach ($requests as $req)
                                    @php
                                        $isCanceled = $req->status === 'done' && $req->is_canceled;
                                        $statusBadge = match($req->status) {
                                            'done' => $isCanceled ? 'bg-rose-500/15 text-rose-300 border-rose-500/30' : 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                                            'doing' => 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30',
                                            'assigned' => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
                                            default => 'bg-slate-500/15 text-slate-400 border-slate-500/30',
                                        };
                                        $statusLabel = match($req->status) {
                                            'done' => $isCanceled ? __('CANCELED') : __('Completed'),
                                            'doing' => __('Doing'),
                                            'assigned' => __('Assigned'),
                                            default => __('Backlog'),
                                        };
                                    @endphp
                                    <tr
                                        wire:click="openDetailFromHistory({{ $req->id }})"
                                        class="hover:bg-slate-800/50 transition-colors cursor-pointer group"
                                        title="{{ __('Click to open details') }}"
                                    >
                                        {{-- Title & Dept --}}
                                        <td class="py-3 px-4 max-w-xs">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-white group-hover:text-indigo-300 transition-colors truncate {{ $isCanceled ? 'line-through text-slate-400' : '' }}">
                                                    {{ $req->title }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5 mt-1">
                                                <span class="px-1.5 py-0.2 rounded text-[10px] bg-slate-800 text-slate-300 font-mono">
                                                    W{{ $req->weight }}
                                                </span>
                                                @if ($isCanceled)
                                                    <span class="text-[10px] text-slate-500 font-bold line-through">
                                                        ⚡ 0 pts
                                                    </span>
                                                @else
                                                    <span class="text-[10px] text-amber-400 font-bold">
                                                        ⚡ {{ $req->bounty_points }} pts
                                                    </span>
                                                @endif
                                                @if ($req->department)
                                                    <span
                                                        class="px-1.5 py-0.2 rounded text-[10px] font-semibold border truncate max-w-[120px]"
                                                        style="background-color: {{ $req->department->color }}15; color: {{ $req->department->color }}; border-color: {{ $req->department->color }}30;"
                                                    >
                                                        {{ $req->department->name }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- Status --}}
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $statusBadge }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </td>

                                        {{-- Requester / Facility --}}
                                        <td class="py-3 px-4">
                                            <div class="font-medium text-slate-200">
                                                {{ $req->requester?->name ?? 'User #'.$req->requester_id }}
                                            </div>
                                            @if ($req->facility_name)
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-sky-500/10 text-sky-400 border border-sky-500/20 text-[10px] font-medium mt-0.5">
                                                    🏢 {{ $req->facility_name }}
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Assignee --}}
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            @if ($req->assignee)
                                                <span class="font-medium text-slate-300 flex items-center gap-1">
                                                    <span>👤</span>
                                                    <span>{{ $req->assignee->name }}</span>
                                                </span>
                                            @else
                                                <span class="text-slate-500 italic text-[11px]">{{ __('Unassigned') }}</span>
                                            @endif
                                        </td>

                                        {{-- Created --}}
                                        @php
                                            $modalViewerTz = auth()->user()?->effectiveTimezone() ?? ($tenant->timezone ?? 'Europe/Athens');
                                            $modalReqCreated = $req->created_at?->timezone($modalViewerTz);
                                            $modalReqCompleted = $req->completed_at?->timezone($modalViewerTz);
                                            $modalTzBadge = now()->timezone($modalViewerTz)->format('T');
                                            if (str_starts_with($modalTzBadge, '+') || str_starts_with($modalTzBadge, '-')) {
                                                $modalTzBadge = 'UTC'.$modalTzBadge;
                                            }
                                        @endphp
                                        <td class="py-3 px-4 whitespace-nowrap text-slate-400 text-[11px] font-mono">
                                            <span>{{ $modalReqCreated?->format('d/m/Y H:i') }}</span>
                                            <span class="ml-1 px-1 py-0.2 rounded text-[9px] font-semibold bg-slate-800 text-slate-400 border border-slate-700/60" title="{{ $modalViewerTz }}">{{ $modalTzBadge }}</span>
                                        </td>

                                        {{-- Completed --}}
                                        <td class="py-3 px-4 whitespace-nowrap text-[11px] font-mono">
                                            @if ($modalReqCompleted)
                                                <span class="text-emerald-400 font-semibold">✓ {{ $modalReqCompleted->format('d/m/Y H:i') }}</span>
                                                <span class="ml-1 px-1 py-0.2 rounded text-[9px] font-semibold bg-slate-800 text-slate-400 border border-slate-700/60" title="{{ $modalViewerTz }}">{{ $modalTzBadge }}</span>
                                            @else
                                                <span class="text-slate-600">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Modal Footer --}}
            <div class="px-6 py-3 border-t border-slate-800 bg-slate-900/90 flex items-center justify-between text-xs text-slate-400 shrink-0">
                <span>{{ __('Showing up to :count requests', ['count' => $requests->count()]) }}</span>
                <button
                    wire:click="closeRequestsHistoryModal"
                    type="button"
                    class="px-4 py-1.5 rounded-xl text-xs font-semibold text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 transition-colors cursor-pointer"
                >
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
@endif
