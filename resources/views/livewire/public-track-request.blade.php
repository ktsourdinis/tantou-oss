<div wire:poll.10s class="min-h-screen bg-slate-950 py-10 px-4 sm:px-6 lg:px-8 selection:bg-indigo-500 selection:text-white">
    <div class="max-w-3xl mx-auto space-y-6">
        {{-- Top Navigation & Branding --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-400 flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-indigo-600/30">
                    担
                </div>
                <div>
                    <h1 class="text-sm font-bold text-white tracking-tight flex items-center gap-2">
                        <span>{{ $postIt->tenant?->name }}</span>
                    </h1>
                    <p class="text-xs text-slate-400">{{ __('Live Request Tracker') }}</p>
                </div>
            </div>

            {{-- Actions & Locale --}}
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-900 border border-slate-800 text-xs">
                    <form action="{{ route('locale.switch', ['locale' => 'el']) }}" method="POST" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all {{ app()->getLocale() === 'el' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white' }}"
                        >
                            ΕΛ
                        </button>
                    </form>
                    <form action="{{ route('locale.switch', ['locale' => 'en']) }}" method="POST" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white' }}"
                        >
                            EN
                        </button>
                    </form>
                </div>

                @if ($postIt->tenant?->public_submission_enabled)
                    <a
                        href="{{ route('public.submit', ['tenantSlug' => $postIt->tenant->slug]) }}"
                        class="text-xs text-indigo-400 hover:text-indigo-300 font-medium hidden sm:inline-block"
                    >
                        + {{ __('New Request') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- Main Tracker Header Card --}}
        <div class="p-6 sm:p-8 rounded-3xl bg-slate-900 border border-slate-800 shadow-2xl space-y-6">
            {{-- Token, Facility, Date Header --}}
            <div class="flex flex-wrap items-center justify-between gap-3 pb-6 border-b border-slate-800">
                <div class="flex flex-wrap items-center gap-2">
                    <div
                        x-data="{ copied: false }"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 font-mono text-xs font-semibold"
                    >
                        <span>#{{ $postIt->tracking_token }}</span>
                        <button
                            type="button"
                            @click="navigator.clipboard.writeText('{{ url()->current() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="p-0.5 hover:text-white transition-colors cursor-pointer"
                            title="{{ __('Copy link to this tracker') }}"
                        >
                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <span x-show="copied" class="text-[10px] text-emerald-400 font-bold">✓</span>
                        </button>
                    </div>

                    @if ($postIt->facility_name)
                        <span class="px-2.5 py-1 rounded-xl bg-slate-800 border border-slate-700/80 text-xs text-slate-300 font-medium flex items-center gap-1">
                            <span>📍</span>
                            <span>{{ $postIt->facility_name }}</span>
                        </span>
                    @endif

                    @if ($postIt->department)
                        <span class="px-2.5 py-1 rounded-xl bg-slate-800/80 border border-slate-700/60 text-xs text-indigo-300 font-medium">
                            {{ $postIt->department->name }}
                        </span>
                    @endif

                    @if ($postIt->status === 'done' && $postIt->is_canceled)
                        <span class="px-2.5 py-1 rounded-xl bg-rose-500/20 text-rose-300 border border-rose-500/40 text-xs font-semibold flex items-center gap-1">
                            <span>✖</span>
                            <span>{{ __('CANCELED') }}</span>
                        </span>
                    @elseif ($postIt->isClosedByRequester())
                        <span class="px-2.5 py-1 rounded-xl bg-teal-500/20 text-teal-300 border border-teal-500/40 text-xs font-semibold flex items-center gap-1">
                            <span>✓</span>
                            <span>{{ __('Closed by Requester') }}</span>
                        </span>
                    @endif
                </div>

                <div class="text-xs text-slate-400">
                    <span>{{ __('Submitted') }} {{ $postIt->created_at->diffForHumans() }}</span>
                </div>
            </div>

            {{-- Title & Requester info --}}
            <div class="space-y-2">
                <h2 class="text-xl sm:text-2xl font-bold text-white leading-tight">
                    {{ $postIt->title }}
                </h2>
                <div class="flex items-center gap-3 text-xs text-slate-400">
                    <span>{{ __('Requester:') }} <strong class="text-slate-200">{{ $postIt->requesterDisplayName() }}</strong></span>
                    @if ($postIt->guest_contact)
                        <span>•</span>
                        <span class="font-mono">{{ $postIt->guest_contact }}</span>
                    @endif
                </div>
            </div>

            {{-- 4-Step Visual Progress Bar --}}
            <div class="pt-4">
                <div class="relative">
                    {{-- Connecting Bar --}}
                    <div class="absolute top-5 left-6 right-6 h-1 bg-slate-800 rounded -z-0">
                        <div
                            class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 rounded transition-all duration-500"
                            style="width: {{ $step === 1 ? '10%' : ($step === 2 ? '40%' : ($step === 3 ? '75%' : '100%')) }}"
                        ></div>
                    </div>

                    {{-- Step Nodes --}}
                    <div class="grid grid-cols-4 gap-2 relative z-10 text-center">
                        {{-- Step 1: Submitted --}}
                        <div class="flex flex-col items-center">
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-xs transition-all {{ $step >= 1 ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/40 ring-4 ring-indigo-950' : 'bg-slate-800 text-slate-400' }}">
                                @if ($step > 1) ✓ @else 1 @endif
                            </div>
                            <span class="text-[11px] font-semibold mt-2 {{ $step >= 1 ? 'text-white' : 'text-slate-500' }}">
                                {{ __('Submitted') }}
                            </span>
                            <span class="text-[10px] text-slate-400 hidden sm:block">{{ __('In Backlog') }}</span>
                        </div>

                        {{-- Step 2: Assigned --}}
                        <div class="flex flex-col items-center">
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-xs transition-all {{ $step >= 2 ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/40 ring-4 ring-indigo-950' : 'bg-slate-800 text-slate-400' }}">
                                @if ($step > 2) ✓ @else 2 @endif
                            </div>
                            <span class="text-[11px] font-semibold mt-2 {{ $step >= 2 ? 'text-white' : 'text-slate-500' }}">
                                {{ __('Assigned') }}
                            </span>
                            <span class="text-[10px] text-slate-400 truncate max-w-[90px] hidden sm:block">
                                {{ $postIt->assignee?->name ?? __('Pending') }}
                            </span>
                        </div>

                        {{-- Step 3: In Progress --}}
                        <div class="flex flex-col items-center">
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-xs transition-all {{ $step >= 3 ? 'bg-amber-600 text-white shadow-lg shadow-amber-600/40 ring-4 ring-amber-950' : 'bg-slate-800 text-slate-400' }}">
                                @if ($step > 3) ✓ @else 3 @endif
                            </div>
                            <span class="text-[11px] font-semibold mt-2 {{ $step >= 3 ? 'text-white' : 'text-slate-500' }}">
                                {{ __('In Progress') }}
                            </span>
                            <span class="text-[10px] text-slate-400 hidden sm:block">{{ __('Working on it') }}</span>
                        </div>

                        {{-- Step 4: Done / Canceled --}}
                        <div class="flex flex-col items-center">
                            @if ($step >= 4 && $postIt->is_canceled)
                                <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-xs transition-all bg-rose-600 text-white shadow-lg shadow-rose-600/40 ring-4 ring-rose-950">
                                    ✖
                                </div>
                                <span class="text-[11px] font-semibold mt-2 text-rose-400">
                                    {{ __('CANCELED') }}
                                </span>
                            @else
                                <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-xs transition-all {{ $step >= 4 ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/40 ring-4 ring-emerald-950' : 'bg-slate-800 text-slate-400' }}">
                                    4
                                </div>
                                <span class="text-[11px] font-semibold mt-2 {{ $step >= 4 ? 'text-emerald-400' : 'text-slate-500' }}">
                                    {{ __('Completed') }}
                                </span>
                            @endif
                            <span class="text-[10px] text-slate-400 hidden sm:block">
                                {{ $postIt->completed_at ? $postIt->completed_at->diffForHumans() : __('Finished') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Live Queue Position Indicator Card --}}
            @php
                $isDoneAndCanceled = ($step === 4 && $postIt->is_canceled);
            @endphp
            <div class="p-4 rounded-2xl border transition-all {{ $isDoneAndCanceled ? 'bg-rose-950/30 border-rose-500/30 text-rose-300' : ($step === 4 ? 'bg-emerald-950/30 border-emerald-500/30 text-emerald-300' : ($queuePosition !== null ? 'bg-indigo-950/40 border-indigo-500/30 text-indigo-300' : 'bg-slate-950/60 border-slate-800 text-slate-300')) }}">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">
                        @if ($isDoneAndCanceled) ✖ @elseif ($step === 4) 🎉 @elseif ($queuePosition !== null) ⚡ @else ⏳ @endif
                    </span>
                    <div class="flex-1">
                        @if ($isDoneAndCanceled)
                            <h3 class="text-xs font-bold text-white">{{ __('Request Canceled') }}</h3>
                            <p class="text-[11px] text-rose-400 mt-0.5">
                                {{ __('This request was canceled before work started.') }}
                            </p>
                        @elseif ($step === 4)
                            <h3 class="text-xs font-bold text-white">{{ __('Request Completed!') }}</h3>
                            <p class="text-[11px] text-emerald-400 mt-0.5">
                                {{ __('This request has been resolved and marked complete.') }}
                            </p>
                        @elseif ($queuePosition !== null)
                            <div class="flex items-center gap-2">
                                <h3 class="text-xs font-bold text-white">
                                    {{ __('Queue Position: #:position in active queue', ['position' => $queuePosition]) }}
                                </h3>
                                <span class="px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 text-[10px] font-mono font-bold">
                                    #{{ $queuePosition }} / {{ $queueTotal }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                {{ __('Assigned to :name. Position updates automatically as tasks are completed.', ['name' => $postIt->assignee?->name ?? __('Staff')]) }}
                            </p>
                        @else
                            <h3 class="text-xs font-bold text-white">{{ __('In Team Backlog Queue') }}</h3>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                {{ __('Your request has been queued in the backlog and is awaiting assignment to a technician.') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Guest Self-Close Action Card --}}
            @if ($postIt->status !== 'done')
                <div x-data="{ showClosePrompt: false }" class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-400 font-bold">✓</span>
                            <div>
                                <h4 class="text-xs font-semibold text-white">{{ __('Already resolved?') }}</h4>
                                <p class="text-[11px] text-slate-400">{{ __('If the issue has been resolved or is no longer needed, you can self-close this request.') }}</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            x-show="!showClosePrompt"
                            @click="showClosePrompt = true"
                            class="shrink-0 px-3.5 py-1.5 rounded-xl bg-teal-600/90 hover:bg-teal-500 text-white text-xs font-semibold shadow-md shadow-teal-600/30 transition-all cursor-pointer flex items-center gap-1.5"
                        >
                            <span>✓</span>
                            <span>{{ __('Issue Resolved — Close Request') }}</span>
                        </button>
                    </div>

                    <div
                        x-show="showClosePrompt"
                        x-cloak
                        class="pt-3 border-t border-slate-800/80 space-y-2.5 animate-fadeIn"
                    >
                        <label for="guestCloseReason" class="block text-xs font-semibold text-slate-300">
                            {{ __('Optional note (e.g. Internet connection restored)...') }}
                        </label>
                        <input
                            wire:model="guestCloseReason"
                            id="guestCloseReason"
                            type="text"
                            maxlength="255"
                            placeholder="{{ __('Optional note (e.g. Internet connection restored)...') }}"
                            class="w-full px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-teal-500"
                        >
                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button
                                type="button"
                                @click="showClosePrompt = false"
                                class="px-3 py-1.5 rounded-xl text-slate-400 hover:text-white text-xs transition-colors cursor-pointer"
                            >
                                {{ __('Cancel') }}
                            </button>
                            <button
                                type="button"
                                wire:click="closeByGuest"
                                class="px-4 py-1.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-semibold shadow-md shadow-teal-600/30 transition-all cursor-pointer flex items-center gap-1.5"
                            >
                                <span>✓</span>
                                <span>{{ __('Confirm Close') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Description & Attachments Box --}}
            @if ($postIt->description || $postIt->attachments->isNotEmpty())
                <div class="pt-4 border-t border-slate-800 space-y-4">
                    @if ($postIt->description)
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                                {{ __('Request Description') }}
                            </h3>
                            <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 text-xs text-slate-300 leading-relaxed whitespace-pre-line">
                                {{ $postIt->description }}
                            </div>
                        </div>
                    @endif

                    @if ($postIt->attachments->isNotEmpty())
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                                {{ __('Attachments') }} ({{ $postIt->attachments->count() }})
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                @foreach ($postIt->attachments as $attachment)
                                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-950/80 border border-slate-800 text-xs">
                                        <div class="flex items-center gap-2 truncate">
                                            <span>📎</span>
                                            <span class="truncate font-mono text-slate-200">{{ $attachment->original_name }}</span>
                                        </div>
                                        <span class="text-[11px] text-slate-500 shrink-0 font-mono">
                                            {{ $attachment->formattedSize() }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Updates & Comments Feed --}}
        <div class="p-6 sm:p-8 rounded-3xl bg-slate-900 border border-slate-800 shadow-2xl space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="text-lg">💬</span>
                    <h3 class="text-sm font-bold text-white">{{ __('Updates & Discussion') }}</h3>
                </div>
                <span class="text-xs text-slate-500 font-mono">{{ $postIt->comments->count() }}</span>
            </div>

            {{-- Comments List --}}
            <div class="space-y-3">
                @forelse ($postIt->comments as $comment)
                    <div class="p-4 rounded-2xl border transition-all {{ $comment->user ? 'bg-indigo-950/20 border-indigo-500/20' : 'bg-slate-950/70 border-slate-800/80' }}">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="font-semibold {{ $comment->user ? 'text-indigo-400' : 'text-slate-300' }} flex items-center gap-1.5">
                                @if ($comment->user)
                                    <span class="px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300 text-[10px] font-bold">{{ __('Staff') }}</span>
                                @endif
                                <span>{{ $comment->authorDisplayName() }}</span>
                            </span>
                            <span class="text-[11px] text-slate-500">
                                {{ $comment->created_at->diffForHumans() }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-200 leading-relaxed whitespace-pre-line">
                            {{ $comment->body }}
                        </p>
                    </div>
                @empty
                    <div class="p-6 text-center rounded-2xl bg-slate-950/40 border border-slate-800/60 text-xs text-slate-400">
                        {{ __('No updates yet. Staff responses will appear here.') }}
                    </div>
                @endforelse
            </div>

            {{-- Guest Comment Reply Box --}}
            @if ($postIt->status !== 'done')
                <form wire:submit.prevent="addComment" class="pt-4 border-t border-slate-800 space-y-3">
                    <label for="newCommentBody" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                        {{ __('Post a Follow-Up Note / Update') }}
                    </label>
                    <textarea
                        wire:model="newCommentBody"
                        id="newCommentBody"
                        rows="3"
                        required
                        maxlength="1000"
                        placeholder="{{ __('Type a message or provide additional info for the technician...') }}"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-950/70 border {{ $errors->has('newCommentBody') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all resize-none"
                    ></textarea>
                    @error('newCommentBody')
                        <p class="text-xs text-rose-400">{{ $message }}</p>
                    @enderror

                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-slate-500">
                            {{ __('Posting as:') }} <strong class="text-slate-300">{{ $postIt->guest_name ?: __('Guest') }}</strong>
                        </span>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/30 transition-all cursor-pointer flex items-center gap-1.5"
                        >
                            <span wire:loading.remove wire:target="addComment">{{ __('Send Update') }}</span>
                            <span wire:loading wire:target="addComment">{{ __('Sending...') }}</span>
                        </button>
                    </div>
                </form>
            @else
                <div class="p-3.5 rounded-2xl bg-emerald-950/20 border border-emerald-500/20 text-center text-xs text-emerald-400">
                    {{ __('This request is marked as completed. Comments are closed.') }}
                </div>
            @endif
        </div>
    </div>
</div>
