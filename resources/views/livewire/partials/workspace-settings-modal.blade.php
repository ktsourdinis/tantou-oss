@if ($showWorkspaceSettingsModal)
    @php
        $modalTenant = \App\Models\Tenant::find($tenantId);
        $canStrictIsolation = $modalTenant ? $modalTenant->canUseFeature('strict_b2b_isolation') : true;
        $canGdprPrivacy = $modalTenant ? $modalTenant->canUseFeature('gdpr_privacy_mode') : true;
        $canCustomTelegram = $modalTenant ? $modalTenant->canUseFeature('custom_telegram_bot') : true;
        $canCustomDomain = $modalTenant ? $modalTenant->canUseFeature('custom_domain') : true;
    @endphp
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md transition-all animate-fadeIn"
        x-data="{ settingsTab: 'general', billingInterval: 'monthly' }"
        @keydown.escape.window="$wire.closeWorkspaceSettingsModal()"
    >
        <div
            @click.away="$wire.closeWorkspaceSettingsModal()"
            class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full max-h-[88vh] shadow-2xl overflow-hidden flex flex-col"
        >
            <form wire:submit.prevent="saveWorkspaceSettings" class="flex flex-col h-full max-h-[88vh] overflow-hidden">
                {{-- Sticky Header --}}
                <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/95 shrink-0 space-y-3.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="text-lg">⚙️</span>
                            <div>
                                <h3 class="text-sm font-bold text-white">{{ __('Workspace Settings') }}</h3>
                                <p class="text-[11px] text-slate-400">{{ __('Manage organization branding, access policies, and integrations.') }}</p>
                            </div>
                        </div>
                        <button
                            wire:click="closeWorkspaceSettingsModal"
                            type="button"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer text-xs"
                            title="{{ __('Close') }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    {{-- Tab Navigation Bar --}}
                    <div class="flex items-center gap-1.5 p-1 bg-slate-950/70 rounded-xl border border-slate-800 text-xs">
                        <button
                            type="button"
                            @click="settingsTab = 'general'"
                            :class="settingsTab === 'general' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:text-slate-200 font-medium'"
                            class="flex-1 py-1.5 px-3 rounded-lg transition-all text-center cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <span>🏢</span>
                            <span>{{ __('General') }}</span>
                            @if ($errors->has('tenantName') || $errors->has('tenantSlug') || $errors->has('tenantTimezone'))
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                            @endif
                        </button>

                        <button
                            type="button"
                            @click="settingsTab = 'access'"
                            :class="settingsTab === 'access' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:text-slate-200 font-medium'"
                            class="flex-1 py-1.5 px-3 rounded-lg transition-all text-center cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <span>🛡️</span>
                            <span>{{ __('Access & Drop-Box') }}</span>
                            @if ($errors->has('strictClientIsolation') || $errors->has('publicSubmissionEnabled'))
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                            @endif
                        </button>

                        <button
                            type="button"
                            @click="settingsTab = 'telegram'"
                            :class="settingsTab === 'telegram' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:text-slate-200 font-medium'"
                            class="flex-1 py-1.5 px-3 rounded-lg transition-all text-center cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <span>✈️</span>
                            <span>{{ __('Telegram Bot') }}</span>
                            @if ($errors->has('telegramEnabled') || $errors->has('telegramPrivacyMode') || $errors->has('telegramBotUsername') || $errors->has('telegramBotToken') || $errors->has('telegramWebhookSecret'))
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                            @endif
                        </button>

                        @if(\App\Support\Edition::isCloud())
                            <button
                                type="button"
                                @click="settingsTab = 'billing'"
                                :class="settingsTab === 'billing' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:text-slate-200 font-medium'"
                                class="flex-1 py-1.5 px-3 rounded-lg transition-all text-center cursor-pointer flex items-center justify-center gap-1.5"
                            >
                                <span>💳</span>
                                <span>{{ __('Plan & Billing') }}</span>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Scrollable Body --}}
                <div class="flex-1 overflow-y-auto p-5 space-y-4">
                    {{-- Tab 1: General --}}
                    <div x-show="settingsTab === 'general'" class="space-y-4">
                        {{-- Organization Name --}}
                        <div>
                            <label for="settingsTenantName" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                                {{ __('Organization Name') }}
                            </label>
                            <input
                                wire:model.live.debounce.300ms="tenantName"
                                id="settingsTenantName"
                                type="text"
                                required
                                class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('tenantName') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                                placeholder="e.g. Acme Corporation"
                            >
                            @error('tenantName')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Subdomain / Slug --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="settingsTenantSlug" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                                    {{ __('Workspace Subdomain (Slug)') }}
                                </label>
                                <button
                                    wire:click="suggestSlugsFromTenantName"
                                    type="button"
                                    class="text-[11px] font-medium text-indigo-400 hover:text-indigo-300 transition-colors cursor-pointer"
                                >
                                    {{ __('Suggest from Organization Name') }}
                                </button>
                            </div>

                            <div class="relative flex items-center">
                                <input
                                    wire:model.live.debounce.300ms="tenantSlug"
                                    id="settingsTenantSlug"
                                    type="text"
                                    required
                                    class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('tenantSlug') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all font-mono {{ \App\Support\Edition::usesSubdomains(request()) ? 'pr-28' : '' }}"
                                    placeholder="acme"
                                >
                                @if (\App\Support\Edition::usesSubdomains(request()))
                                    <div class="absolute right-3 text-xs font-mono text-slate-500 pointer-events-none">
                                        .{{ \App\Support\Edition::baseDomain() }}
                                    </div>
                                @endif
                            </div>

                            <div class="mt-1.5 flex items-center justify-between text-xs text-slate-400">
                                <span>{{ __('Live Preview:') }}</span>
                                <span class="font-mono text-indigo-400 font-medium">
                                    @if (\App\Support\Edition::usesSubdomains(request()))
                                        {{ strtolower(trim($tenantSlug ?: 'workspace')) }}.{{ \App\Support\Edition::baseDomain() }}
                                    @else
                                        {{ request()->getHttpHost() }}/board?workspace={{ strtolower(trim($tenantSlug ?: 'workspace')) }}
                                    @endif
                                </span>
                            </div>

                            @error('tenantSlug')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Smart Slug Suggestions Chips --}}
                        @if (! empty($suggestedSlugs))
                            <div class="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs">
                                <p class="text-amber-300 font-medium text-xs mb-2 flex items-center gap-1.5">
                                    <span>⚠️</span>
                                    <span>{{ __('This subdomain is already taken. Available suggestions:') }}</span>
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($suggestedSlugs as $suggested)
                                        <button
                                            type="button"
                                            wire:click="selectSuggestedSlug('{{ $suggested }}')"
                                            class="px-3 py-1 rounded-xl bg-slate-950/80 border border-indigo-500/30 hover:border-indigo-500 text-indigo-300 hover:text-white font-mono text-xs transition-all cursor-pointer shadow-sm hover:scale-105"
                                            title="{{ __('Click to select this subdomain') }}"
                                        >
                                            {{ $suggested }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Organization Default Timezone --}}
                        <div>
                            <label for="settingsTenantTimezone" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                                <span>🕒</span>
                                <span>{{ __('Organization Default Timezone') }}</span>
                            </label>
                            <select
                                wire:model="tenantTimezone"
                                id="settingsTenantTimezone"
                                class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('tenantTimezone') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-4 transition-all"
                            >
                                @foreach (\App\Support\Timezone::all() as $tzValue => $tzLabel)
                                    <option value="{{ $tzValue }}">{{ $tzLabel }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-slate-400">
                                {{ __('Default timezone for SLA metrics, daily/weekly task grouping, and team analytics.') }}
                            </p>
                            @error('tenantTimezone')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Custom Domain (Business Plan) --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="settingsTenantCustomDomain" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🌐</span>
                                    <span>{{ __('Branded Custom Domain') }}</span>
                                </label>
                                @if (! $canCustomDomain)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        🔒 {{ __('Business Plan') }}
                                    </span>
                                @endif
                            </div>

                            <input
                                wire:model="tenantCustomDomain"
                                id="settingsTenantCustomDomain"
                                type="text"
                                @disabled(! $canCustomDomain)
                                class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('tenantCustomDomain') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all font-mono {{ ! $canCustomDomain ? 'opacity-50 cursor-not-allowed' : '' }}"
                                placeholder="ops.yourcompany.com"
                            >
                            <p class="mt-1 text-[11px] text-slate-400">
                                {{ __('Point a CNAME record to your Tantou server to access this workspace under your own brand.') }}
                            </p>
                            @error('tenantCustomDomain')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Tab 2: Access & Drop-Box --}}
                    <div x-show="settingsTab === 'access'" class="space-y-4" style="display: none;">
                        {{-- Strict B2B Client Isolation Toggle --}}
                        <div>
                            <label class="flex items-start gap-3 p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 {{ $canStrictIsolation ? 'hover:border-slate-700 cursor-pointer' : 'opacity-60 cursor-not-allowed' }} transition-all">
                                <div class="relative inline-flex items-center mt-0.5 shrink-0">
                                    <input
                                        wire:model="strictClientIsolation"
                                        type="checkbox"
                                        class="sr-only peer"
                                        @disabled(! $canStrictIsolation)
                                    >
                                    <div class="w-10 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                </div>
                                <div>
                                    <span class="block text-xs font-bold text-white flex items-center gap-1.5">
                                        <span>🔒</span>
                                        <span>{{ __('Strict B2B Client Isolation') }}</span>
                                        @if (! $canStrictIsolation)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                🔒 {{ __('Business Plan') }}
                                            </span>
                                        @endif
                                    </span>
                                    <span class="block text-[11px] text-slate-400 mt-1 leading-relaxed">
                                        {{ __('When enabled, Client users can only view details of requests submitted by themselves or their own Facility/Organization. Other cards on public columns appear anonymized.') }}
                                    </span>
                                </div>
                            </label>
                            @error('strictClientIsolation')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Public Guest Drop-Box Toggle & Link --}}
                        <div class="space-y-3">
                            <label class="flex items-start gap-3 p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 hover:border-slate-700 cursor-pointer transition-all">
                                <div class="relative inline-flex items-center mt-0.5 shrink-0">
                                    <input
                                        wire:model.live="publicSubmissionEnabled"
                                        type="checkbox"
                                        class="sr-only peer"
                                    >
                                    <div class="w-10 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                </div>
                                <div>
                                    <span class="block text-xs font-bold text-white flex items-center gap-1.5">
                                        <span>📥</span>
                                        <span>{{ __('Public Guest Drop-Box (No-Login Submission)') }}</span>
                                    </span>
                                    <span class="block text-[11px] text-slate-400 mt-1 leading-relaxed">
                                        {{ __('Allow staff or visitors to submit requests straight to the Backlog via a public link or QR code without needing a user account.') }}
                                    </span>
                                </div>
                            </label>

                            @if ($publicSubmissionEnabled)
                                <div
                                    x-data="{ copied: false }"
                                    class="p-3.5 rounded-2xl bg-emerald-950/30 border border-emerald-500/20 space-y-2.5 animate-fadeIn"
                                >
                                    <div class="flex items-center justify-between text-xs font-semibold text-emerald-400">
                                        <span class="flex items-center gap-1.5">
                                            <span>🔗</span>
                                            <span>{{ __('Shareable Request Link') }}</span>
                                        </span>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ __('Direct Backlog Access') }}</span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <input
                                            type="text"
                                            readonly
                                            value="{{ $this->publicDropBoxUrl }}"
                                            class="w-full px-3 py-1.5 text-xs font-mono bg-slate-950/80 border border-slate-800 rounded-xl text-slate-200 select-all focus:outline-none focus:border-emerald-500/50"
                                        >
                                        <button
                                            type="button"
                                            @click="copyToClipboard('{{ $this->publicDropBoxUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="shrink-0 px-3 py-1.5 rounded-xl bg-emerald-600/90 hover:bg-emerald-500 text-white text-xs font-medium transition-all flex items-center gap-1.5 cursor-pointer shadow-sm shadow-emerald-900/30"
                                        >
                                            <span x-show="!copied">{{ __('Copy Link') }}</span>
                                            <span x-show="copied" class="flex items-center gap-1">
                                                <span>✓</span>
                                                <span>{{ __('Copied!') }}</span>
                                            </span>
                                        </button>
                                    </div>

                                    <p class="text-[11px] text-slate-400 leading-normal flex items-start gap-1.5">
                                        <span class="text-emerald-400 font-bold shrink-0">💡</span>
                                        <span>
                                            {{ __('Tip: Append') }} <code class="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 text-emerald-300 font-mono text-[10px]">?facility=Ακτινολογικό</code> {{ __('to pre-fill the facility/unit automatically.') }}
                                        </span>
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tab 3: Telegram Bot --}}
                    <div x-show="settingsTab === 'telegram'" class="space-y-4" style="display: none;">
                        <label class="flex items-start gap-3 p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 hover:border-slate-700 cursor-pointer transition-all">
                            <div class="relative inline-flex items-center mt-0.5 shrink-0">
                                <input
                                    wire:model="telegramEnabled"
                                    type="checkbox"
                                    class="sr-only peer"
                                >
                                <div class="w-10 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                            </div>
                            <div>
                                <span class="block text-xs font-bold text-white flex items-center gap-1.5">
                                    <span>✈️</span>
                                    <span>{{ __('Enable Telegram Notifications & Bot') }}</span>
                                </span>
                                <span class="block text-[11px] text-slate-400 mt-1 leading-relaxed">
                                    {{ __('Enable real-time backlog alerts, personal task assignments, and 2-way interactive inline bot actions via Telegram.') }}
                                </span>
                            </div>
                        </label>

                        @if ($telegramEnabled)
                            <div class="p-4 rounded-2xl bg-slate-950/70 border border-sky-500/20 space-y-3.5 animate-fadeIn">
                                {{-- Privacy Mode Toggle --}}
                                <div>
                                    <label class="flex items-start gap-3 {{ $canGdprPrivacy ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed' }}">
                                        <div class="relative inline-flex items-center mt-0.5 shrink-0">
                                            <input
                                                wire:model="telegramPrivacyMode"
                                                type="checkbox"
                                                class="sr-only peer"
                                                @disabled(! $canGdprPrivacy)
                                            >
                                            <div class="w-9 h-4.5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-amber-500"></div>
                                        </div>
                                        <div>
                                            <span class="block text-xs font-bold text-slate-200 flex items-center gap-1.5">
                                                <span>🔒</span>
                                                <span>{{ __('GDPR / Privacy Mode (Redact Titles & Descriptions)') }}</span>
                                                @if (! $canGdprPrivacy)
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                        🔒 {{ __('Business Plan') }}
                                                    </span>
                                                @endif
                                            </span>
                                            <span class="block text-[10px] text-slate-400 mt-0.5 leading-relaxed">
                                                {{ __('When enabled, Telegram alerts omit sensitive task titles/descriptions and only show tokenized metadata (#TNT-XXXX, Facility, Department, Priority, Weight).') }}
                                            </span>
                                        </div>
                                    </label>
                                    @error('telegramPrivacyMode')
                                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Custom Bot Credentials (Optional) --}}
                                <div class="pt-2 border-t border-slate-800/80 space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <span class="block text-[11px] font-bold text-slate-300 uppercase tracking-wider">
                                            {{ __('Custom Telegram Bot (Optional)') }}
                                        </span>
                                        @if (! $canCustomTelegram)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                🔒 {{ __('Business Plan') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[10px] text-slate-400">
                                        {{ __('Leave blank to use the platform bot. Enter custom bot credentials from @BotFather if this organization uses a dedicated bot.') }}
                                    </p>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
                                                {{ __('Bot Username') }}
                                            </label>
                                            <input
                                                wire:model="telegramBotUsername"
                                                type="text"
                                                @disabled(! $canCustomTelegram)
                                                placeholder="e.g. AcmeTantouBot"
                                                class="w-full px-3 py-1.5 text-xs bg-slate-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 {{ ! $canCustomTelegram ? 'opacity-50 cursor-not-allowed' : '' }}"
                                            >
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
                                                {{ __('Bot API Token') }}
                                            </label>
                                            <input
                                                wire:model="telegramBotToken"
                                                type="password"
                                                @disabled(! $canCustomTelegram)
                                                placeholder="{{ __('Leave empty to keep current') }}"
                                                class="w-full px-3 py-1.5 text-xs bg-slate-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 {{ ! $canCustomTelegram ? 'opacity-50 cursor-not-allowed' : '' }}"
                                            >
                                        </div>
                                    </div>
                                    @error('telegramBotToken')
                                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                                    @enderror

                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
                                            {{ __('Webhook Secret Token (Optional)') }}
                                        </label>
                                        <input
                                            wire:model="telegramWebhookSecret"
                                            type="text"
                                            placeholder="{{ __('Secret token for X-Telegram-Bot-Api-Secret-Token') }}"
                                            class="w-full px-3 py-1.5 text-xs font-mono bg-slate-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-sky-500"
                                        >
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Compact 3-Step Setup Quick Guide --}}
                        <div class="p-3.5 rounded-2xl bg-sky-950/30 border border-sky-500/20 text-xs space-y-2">
                            <div class="flex items-center gap-2 text-sky-400 font-semibold text-xs">
                                <span>📖</span>
                                <span>{{ __('Quick Telegram Setup Guide') }}</span>
                            </div>
                            <ol class="space-y-1.5 text-[11px] text-slate-300 list-decimal list-inside">
                                <li>
                                    <span class="text-slate-200 font-medium">{{ __('Get a Bot Token:') }}</span>
                                    {{ __('Message') }} <a href="https://t.me/BotFather" target="_blank" class="text-sky-400 underline hover:text-sky-300">@BotFather</a> {{ __('on Telegram, send') }} <code class="px-1 py-0.5 rounded bg-slate-900 border border-slate-800 text-sky-300 font-mono text-[10px]">/newbot</code>, {{ __('and copy the API token.') }}
                                </li>
                                <li>
                                    <span class="text-slate-200 font-medium">{{ __('Save Credentials:') }}</span>
                                    {{ __('Paste the Bot Username and API Token above (or set TANTOU_TELEGRAM_BOT_TOKEN in .env).') }}
                                </li>
                                <li>
                                    <span class="text-slate-200 font-medium">{{ __('Intranet / Local IP Mode:') }}</span>
                                    {{ __('If running on a private IP/firewall without public HTTPS webhook, run the polling worker:') }}
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <code class="px-2 py-1 rounded bg-slate-900 border border-slate-800 text-sky-300 font-mono text-[10px] select-all flex-1">php artisan tantou:telegram-poll</code>
                                    </div>
                                </li>
                            </ol>
                        </div>

                        {{-- Department Group Links with 1-Click Unlink --}}
                        @php
                            $settingsDepartments = \App\Models\Department::where('tenant_id', $this->tenantId)->get();
                        @endphp
                        @if ($settingsDepartments->isNotEmpty())
                            <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 text-xs space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-300 text-xs flex items-center gap-1.5">
                                        <span>👥</span>
                                        <span>{{ __('Department Telegram Groups & 1-Click Unlink') }}</span>
                                    </span>
                                    <span class="text-[10px] text-slate-500">{{ __('Only for shared group rooms') }}</span>
                                </div>
                                <div class="divide-y divide-slate-800/60 rounded-xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                                    @foreach ($settingsDepartments as $dept)
                                        <div class="p-2.5 flex items-center justify-between gap-2 hover:bg-slate-800/30 transition-colors">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $dept->color }}"></span>
                                                <span class="text-xs font-semibold text-white truncate">{{ $dept->name }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                @if (filled($dept->telegram_chat_id))
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                                        {{ __('Linked') }}
                                                    </span>
                                                    <button
                                                        wire:click="unlinkDepartmentTelegram({{ $dept->id }})"
                                                        wire:confirm="{{ __('Unlink Telegram group from this department?') }}"
                                                        type="button"
                                                        class="text-[11px] font-semibold text-rose-400 hover:text-rose-300 cursor-pointer"
                                                    >
                                                        {{ __('Unlink') }}
                                                    </button>
                                                @else
                                                    <span class="text-[10px] text-slate-500 italic">{{ __('Not Linked') }}</span>
                                                    <button
                                                        wire:click="generateDepartmentTelegramToken({{ $dept->id }})"
                                                        type="button"
                                                        class="text-[11px] font-semibold text-sky-400 hover:text-sky-300 cursor-pointer"
                                                    >
                                                        {{ __('Link') }}
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                        @if ($selectedDepartmentTelegramId === $dept->id && $departmentTelegramToken)
                                            <div
                                                x-data="{ copied: false }"
                                                class="p-2.5 bg-slate-950 border-t border-sky-500/30 space-y-1"
                                            >
                                                <div class="text-[10px] text-sky-400">
                                                    {{ __('Paste into :name Telegram Group:', ['name' => $dept->name]) }}
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <input
                                                        type="text"
                                                        readonly
                                                        value="/link dept_{{ $departmentTelegramToken }}"
                                                        class="w-full px-2 py-1 text-[11px] font-mono bg-slate-900 border border-slate-800 rounded text-white select-all focus:outline-none"
                                                    >
                                                    <button
                                                        type="button"
                                                        @click="copyToClipboard('/link dept_{{ $departmentTelegramToken }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                        class="shrink-0 px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-white text-[11px] font-medium transition-all cursor-pointer"
                                                    >
                                                        <span x-show="!copied">{{ __('Copy') }}</span>
                                                        <span x-show="copied" class="text-emerald-400">✓</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Tab 4: Plan & Billing (Cloud Mode only) --}}
                    @if (\App\Support\Edition::isCloud() && $modalTenant)
                        @php
                            $effPlan = $modalTenant->effectivePlan();
                            $activeWorkers = $modalTenant->activeWorkersCount();
                            $maxWorkers = $modalTenant->maxWorkersLimit();
                            $workersPct = $maxWorkers ? min(100, round(($activeWorkers / $maxWorkers) * 100)) : 100;

                            $deptCount = $modalTenant->departments()->count();
                            $maxDept = $modalTenant->maxDepartmentsLimit();
                            $deptPct = $maxDept ? min(100, round(($deptCount / $maxDept) * 100)) : 100;

                            $status = $modalTenant->subscription_status ?: 'active';
                            $statusBadgeClass = match($status) {
                                'active' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                'trialing' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                'past_due' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                                default => 'bg-slate-700/50 text-slate-300 border-slate-600',
                            };
                        @endphp
                        <div x-show="settingsTab === 'billing'" class="space-y-4" style="display: none;">
                            {{-- Current Plan Overview Card --}}
                            <div class="p-4 rounded-2xl bg-slate-950/70 border border-indigo-500/20 space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                                            {{ __('Current Subscription') }}
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-base font-extrabold text-white">
                                                {{ $effPlan === 'business' ? __('Cloud Business / MSP') : __('Cloud Team') }}
                                            </h4>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBadgeClass }}">
                                                {{ ucfirst($status) }}
                                            </span>
                                            @if ($modalTenant->isDemo())
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/10 text-purple-400 border border-purple-500/30">
                                                    {{ __('Live Demo') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        @if ($modalTenant->stripe_customer_id)
                                            <button
                                                wire:click="openBillingPortal"
                                                type="button"
                                                class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 border border-slate-700 transition-colors cursor-pointer flex items-center gap-1.5"
                                            >
                                                <span>💳</span>
                                                <span>{{ __('Invoices & Cards') }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-y-1 gap-x-4 text-[11px] text-slate-400 pt-2 border-t border-slate-800/80">
                                    @if ($modalTenant->trial_ends_at && $status === 'trialing')
                                        <div>
                                            <span class="text-slate-500">{{ __('Trial ends:') }}</span>
                                            <span class="font-medium text-amber-300 ml-1">{{ $modalTenant->trial_ends_at->format('M d, Y') }}</span>
                                        </div>
                                    @elseif ($modalTenant->current_period_ends_at)
                                        <div>
                                            <span class="text-slate-500">{{ __('Renews on:') }}</span>
                                            <span class="font-medium text-slate-200 ml-1">{{ $modalTenant->current_period_ends_at->format('M d, Y') }}</span>
                                        </div>
                                    @endif
                                    <div>
                                        <span class="text-slate-500">{{ __('Interval:') }}</span>
                                        <span class="font-medium text-slate-200 ml-1">{{ ucfirst($modalTenant->billing_interval ?: 'monthly') }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Usage Meters --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {{-- Workers Meter --}}
                                <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold text-slate-300 flex items-center gap-1">
                                            <span>👥</span>
                                            <span>{{ __('Active Workers') }}</span>
                                        </span>
                                        <span class="font-mono font-bold {{ $maxWorkers && $activeWorkers >= $maxWorkers ? 'text-rose-400' : 'text-slate-200' }}">
                                            {{ $activeWorkers }} / {{ $maxWorkers ?? '∞' }}
                                        </span>
                                    </div>
                                    <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all duration-500 {{ $maxWorkers && $activeWorkers >= $maxWorkers ? 'bg-rose-500' : ($workersPct > 80 ? 'bg-amber-500' : 'bg-indigo-500') }}"
                                            style="width: {{ $maxWorkers ? $workersPct : 100 }}%"
                                        ></div>
                                    </div>
                                    <p class="text-[10px] text-slate-400">
                                        {{ __('Counts Admins, Coordinators, and Collaborators. Clients are unlimited!') }}
                                    </p>
                                </div>

                                {{-- Departments Meter --}}
                                <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold text-slate-300 flex items-center gap-1">
                                            <span>🏢</span>
                                            <span>{{ __('Departments') }}</span>
                                        </span>
                                        <span class="font-mono font-bold text-slate-200">
                                            {{ $deptCount }} / {{ $maxDept ?? '∞' }}
                                        </span>
                                    </div>
                                    <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all duration-500 bg-sky-500"
                                            style="width: {{ $maxDept ? $deptPct : 100 }}%"
                                        ></div>
                                    </div>
                                    <p class="text-[10px] text-slate-400">
                                        {{ __('Capacity queues and team partitions.') }}
                                    </p>
                                </div>
                            </div>

                            {{-- Billing Interval Switcher --}}
                            <div class="flex items-center justify-between pt-1">
                                <span class="text-xs font-semibold text-slate-300">{{ __('Choose Plan & Interval:') }}</span>
                                <div class="inline-flex p-0.5 rounded-xl bg-slate-950 border border-slate-800 text-xs">
                                    <button
                                        type="button"
                                        @click="billingInterval = 'monthly'"
                                        :class="billingInterval === 'monthly' ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-400 hover:text-slate-200 font-medium'"
                                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer"
                                    >
                                        {{ __('Monthly') }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="billingInterval = 'yearly'"
                                        :class="billingInterval === 'yearly' ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-400 hover:text-slate-200 font-medium'"
                                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer flex items-center gap-1"
                                    >
                                        <span>{{ __('Yearly') }}</span>
                                        <span class="text-[9px] px-1 py-0.2 rounded bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30">-20%</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Side-by-side Plan Cards --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {{-- Cloud Team Card --}}
                                <div class="p-4 rounded-2xl bg-slate-950/70 border {{ $effPlan === 'team' ? 'border-sky-500/40 bg-sky-950/10' : 'border-slate-800' }} flex flex-col justify-between space-y-3">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <h5 class="text-sm font-bold text-white">{{ __('Cloud Team') }}</h5>
                                            @if ($effPlan === 'team')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/20 text-sky-300 border border-sky-500/30">
                                                    {{ __('Current') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-lg font-black text-white">
                                            <span x-show="billingInterval === 'monthly'">€29<span class="text-xs font-normal text-slate-400"> /mo</span></span>
                                            <span x-show="billingInterval === 'yearly'">€290<span class="text-xs font-normal text-slate-400"> /yr</span></span>
                                        </div>
                                        <ul class="text-[11px] text-slate-300 space-y-1.5 pt-2 border-t border-slate-800">
                                            <li class="flex items-center gap-1.5">
                                                <span class="text-emerald-400">✓</span>
                                                <span>{{ __('Up to 15 team workers') }}</span>
                                            </li>
                                            <li class="flex items-center gap-1.5">
                                                <span class="text-emerald-400">✓</span>
                                                <span>{{ __('5 departments') }}</span>
                                            </li>
                                            <li class="flex items-center gap-1.5">
                                                <span class="text-emerald-400">✓</span>
                                                <span>{{ __('Public guest drop-box') }}</span>
                                            </li>
                                            <li class="flex items-center gap-1.5">
                                                <span class="text-emerald-400">✓</span>
                                                <span>{{ __('Unlimited client logins') }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="pt-2">
                                        @if ($effPlan === 'team')
                                            <button
                                                type="button"
                                                disabled
                                                class="w-full py-2 px-3 rounded-xl bg-slate-800/80 text-slate-400 text-xs font-semibold cursor-default text-center"
                                            >
                                                {{ __('Active Plan') }}
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                @click="$wire.startStripeCheckout('team', billingInterval)"
                                                class="w-full py-2 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-colors cursor-pointer text-center"
                                            >
                                                {{ __('Switch to Cloud Team') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Cloud Business Card --}}
                                <div class="p-4 rounded-2xl bg-slate-950/70 border {{ $effPlan === 'business' ? 'border-indigo-500/50 bg-indigo-950/15' : 'border-indigo-500/20' }} flex flex-col justify-between space-y-3 relative overflow-hidden">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <h5 class="text-sm font-bold text-white flex items-center gap-1.5">
                                                <span>{{ __('Cloud Business') }}</span>
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">MSP</span>
                                            </h5>
                                            @if ($effPlan === 'business')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                                    {{ __('Current') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-lg font-black text-white">
                                            <span x-show="billingInterval === 'monthly'">€79<span class="text-xs font-normal text-slate-400"> /mo</span></span>
                                            <span x-show="billingInterval === 'yearly'">€790<span class="text-xs font-normal text-slate-400"> /yr</span></span>
                                        </div>
                                        <ul class="text-[11px] text-slate-300 space-y-1.5 pt-2 border-t border-slate-800">
                                            <li class="flex items-center gap-1.5">
                                                <span class="text-emerald-400">✓</span>
                                                <span class="font-semibold text-white">{{ __('Unlimited team workers') }}</span>
                                            </li>
                                            <li class="flex items-center gap-1.5">
                                                <span class="text-emerald-400">✓</span>
                                                <span class="font-semibold text-white">{{ __('Unlimited departments') }}</span>
                                            </li>
                                            <li class="flex items-center gap-1.5">
                                                <span class="text-indigo-400">🛡️</span>
                                                <span>{{ __('Strict B2B client isolation') }}</span>
                                            </li>
                                            <li class="flex items-center gap-1.5">
                                                <span class="text-indigo-400">🔒</span>
                                                <span>{{ __('GDPR / Privacy mode') }}</span>
                                            </li>
                                            <li class="flex items-center gap-1.5">
                                                <span class="text-indigo-400">✈️</span>
                                                <span>{{ __('Custom Telegram bot token') }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="pt-2">
                                        @if ($effPlan === 'business')
                                            <button
                                                type="button"
                                                disabled
                                                class="w-full py-2 px-3 rounded-xl bg-indigo-900/30 border border-indigo-500/30 text-indigo-300 text-xs font-semibold cursor-default text-center"
                                            >
                                                {{ __('Active Plan') }}
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                @click="$wire.startStripeCheckout('business', billingInterval)"
                                                class="w-full py-2 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer text-center"
                                            >
                                                {{ __('Upgrade to Cloud Business') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Sticky Footer --}}
                <div class="shrink-0 border-t border-slate-800 bg-slate-900/95 px-6 py-3.5 flex items-center justify-end gap-3">
                    <button
                        wire:click="closeWorkspaceSettingsModal"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer flex items-center gap-2"
                    >
                        <span wire:loading.remove wire:target="saveWorkspaceSettings">{{ __('Save Changes') }}</span>
                        <span wire:loading wire:target="saveWorkspaceSettings">{{ __('Saving...') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
