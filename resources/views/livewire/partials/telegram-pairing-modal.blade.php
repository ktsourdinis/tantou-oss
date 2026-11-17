@if ($showTelegramModal)
    @php
        $modalDepartments = isset($departments) ? $departments : \App\Models\Department::where('tenant_id', $this->tenantId)->get();
    @endphp
    <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-2xl shadow-2xl p-6 text-left animate-fadeIn max-h-[90vh] overflow-y-auto space-y-5">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="text-xl">✈️</span>
                    <div>
                        <h3 class="text-base font-bold text-white">{{ __('Telegram Bot & Smart Alerts') }}</h3>
                        <p class="text-xs text-slate-400">{{ __('Real-time task notifications, personal 1-on-1 actions & optional department groups') }}</p>
                    </div>
                </div>
                <button wire:click="closeTelegramModal" class="text-slate-400 hover:text-white cursor-pointer transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Two Crystal-Clear Visual Cards --}}
            <div class="space-y-4">
                {{-- CARD 1: STEP 1 (For Every Worker — 1-on-1 Direct Chat with Bot) --}}
                <div class="p-5 rounded-2xl bg-slate-950/70 border border-indigo-500/30 space-y-3.5 shadow-lg relative overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="p-1 rounded-lg bg-indigo-500/20 text-indigo-400 text-xs">👤</span>
                            <span class="text-xs font-bold text-indigo-300 uppercase tracking-wider">
                                {{ __('Step 1: Personal 1-on-1 Worker Pairing') }}
                            </span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                            {{ __('For Every Collaborator') }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-300 leading-relaxed">
                        {{ __('Open the bot in a private 1-on-1 chat and send your personal /start command. You will ONLY receive alerts for tasks assigned to you (plus unassigned backlog tasks in your department).') }}
                    </p>

                    @if (auth()->check() && filled(auth()->user()->telegram_chat_id))
                        {{-- User is Linked --}}
                        <div class="p-3.5 rounded-xl bg-emerald-950/30 border border-emerald-500/30 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-emerald-300 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                    <span>{{ __('Your Account is Linked') }}</span>
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-medium bg-slate-900 border border-slate-800 text-slate-300">
                                    {{ auth()->user()->telegram_username ? '@' . auth()->user()->telegram_username : __('ID: :id', ['id' => auth()->user()->telegram_chat_id]) }}
                                </span>
                            </div>

                            <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between gap-3">
                                <label class="inline-flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleTelegramNotifications"
                                        {{ auth()->user()->telegram_notifications_enabled ? 'checked' : '' }}
                                        class="rounded bg-slate-950 border-slate-800 text-sky-500 focus:ring-0"
                                    >
                                    <span>{{ __('Task Notifications Enabled') }}</span>
                                </label>

                                <button
                                    wire:click="unlinkTelegram"
                                    type="button"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-400 hover:text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition-all cursor-pointer"
                                >
                                    {{ __('Unlink') }}
                                </button>
                            </div>
                        </div>
                    @else
                        {{-- User Not Linked -> 1-Click Deep Link & Manual Token --}}
                        <div class="space-y-3">
                            @if ($telegramPairingUrl)
                                <a
                                    href="{{ $telegramPairingUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-white text-xs font-bold shadow-lg shadow-sky-500/20 transition-all cursor-pointer"
                                >
                                    <span>✈️</span>
                                    <span>{{ __('Open Telegram & Pair (1-Click)') }}</span>
                                </a>
                            @endif

                            <div
                                x-data="{ copied: false }"
                                class="p-3 rounded-xl bg-slate-900 border border-slate-800 space-y-1.5"
                            >
                                <div class="flex items-center justify-between text-[11px] text-slate-400">
                                    <span>{{ __('Or send this manual command to the bot:') }}</span>
                                    <button
                                        type="button"
                                        wire:click="generateTelegramPairingToken"
                                        class="text-sky-400 hover:text-sky-300 text-[10px] font-semibold cursor-pointer"
                                    >
                                        🔄 {{ __('Refresh Code') }}
                                    </button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        readonly
                                        value="/start usr_{{ $telegramPairingToken }}"
                                        class="w-full px-3 py-1.5 text-xs font-mono bg-slate-950 border border-slate-800 rounded-lg text-white select-all focus:outline-none"
                                    >
                                    <button
                                        type="button"
                                        @click="copyToClipboard('/start usr_{{ $telegramPairingToken }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="shrink-0 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white text-xs font-medium transition-all cursor-pointer"
                                    >
                                        <span x-show="!copied">{{ __('Copy') }}</span>
                                        <span x-show="copied" class="text-emerald-400">✓</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- CARD 2: STEP 2 (Optional — Shared Telegram Group per Department) --}}
                <div class="p-5 rounded-2xl bg-slate-950/70 border border-slate-800 space-y-3.5 shadow-lg relative overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="p-1 rounded-lg bg-slate-800 text-slate-400 text-xs">👥</span>
                            <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">
                                {{ __('Step 2: Shared Telegram Group per Department') }}
                            </span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                            {{ __('Optional') }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-300 leading-relaxed">
                        {{ __('Only if you want a shared Telegram Group room for a department: create a Telegram Group, add the bot to the group, and paste /link dept_... inside the Group.') }}
                    </p>

                    @if (auth()->check() && auth()->user()->isAdmin())
                        <div class="pt-2 border-t border-slate-800/80 space-y-2.5">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">
                                {{ __('Workspace Departments & Group Linking:') }}
                            </span>

                            <div class="divide-y divide-slate-800/60 rounded-xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                                @forelse ($modalDepartments as $dept)
                                    <div class="p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 hover:bg-slate-800/30 transition-colors">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="w-3 h-3 rounded-full shrink-0" style="background-color: {{ $dept->color }}"></span>
                                            <div>
                                                <span class="text-xs font-semibold text-white block truncate">{{ $dept->name }}</span>
                                                <span class="text-[10px] font-mono text-slate-400">/{{ $dept->slug }}</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
                                            @if (filled($dept->telegram_chat_id))
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                    <span>{{ __('Linked') }}</span>
                                                </span>

                                                <button
                                                    wire:click="unlinkDepartmentTelegram({{ $dept->id }})"
                                                    wire:confirm="{{ __('Unlink Telegram group from this department?') }}"
                                                    type="button"
                                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-400 hover:text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition-all cursor-pointer"
                                                    title="{{ __('Unlink department group chat') }}"
                                                >
                                                    {{ __('Unlink') }}
                                                </button>
                                            @else
                                                <span class="text-[10px] text-slate-500 italic">{{ __('Not Linked') }}</span>

                                                <button
                                                    wire:click="generateDepartmentTelegramToken({{ $dept->id }})"
                                                    type="button"
                                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold text-sky-400 hover:text-sky-300 bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/20 transition-all cursor-pointer"
                                                >
                                                    {{ __('Get Link Command') }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($selectedDepartmentTelegramId === $dept->id && $departmentTelegramToken)
                                        <div
                                            x-data="{ copied: false }"
                                            class="p-3 bg-slate-950 border-t border-sky-500/30 space-y-1.5 animate-fadeIn"
                                        >
                                            <div class="flex items-center justify-between text-[11px] text-sky-400">
                                                <span>{{ __('Paste this command inside the :name Telegram Group:', ['name' => $dept->name]) }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="text"
                                                    readonly
                                                    value="/link dept_{{ $departmentTelegramToken }}"
                                                    class="w-full px-3 py-1.5 text-xs font-mono bg-slate-900 border border-slate-800 rounded-lg text-white select-all focus:outline-none"
                                                >
                                                <button
                                                    type="button"
                                                    @click="copyToClipboard('/link dept_{{ $departmentTelegramToken }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                    class="shrink-0 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white text-xs font-medium transition-all cursor-pointer"
                                                >
                                                    <span x-show="!copied">{{ __('Copy') }}</span>
                                                    <span x-show="copied" class="text-emerald-400">✓</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                @empty
                                    <div class="p-3 text-center text-xs text-slate-500 italic">
                                        {{ __('No departments found in this workspace.') }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <p class="text-[11px] text-slate-400 italic">
                            {{ __('Department Telegram Groups can be linked by workspace administrators in Department settings.') }}
                        </p>
                    @endif
                </div>
            </div>

            {{-- Footer --}}
            <div class="pt-3 border-t border-slate-800 flex items-center justify-end">
                <button
                    wire:click="closeTelegramModal"
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white transition-colors cursor-pointer"
                >
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
@endif
