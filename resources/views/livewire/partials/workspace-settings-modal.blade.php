@if ($showWorkspaceSettingsModal)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md transition-all animate-fadeIn"
        x-data
        @keydown.escape.window="$wire.closeWorkspaceSettingsModal()"
    >
        <div
            @click.away="$wire.closeWorkspaceSettingsModal()"
            class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col"
        >
            {{-- Modal Header --}}
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 shrink-0">
                <div class="flex items-center gap-2.5">
                    <span class="text-lg">⚙️</span>
                    <div>
                        <h3 class="text-sm font-bold text-white">{{ __('Workspace Settings') }}</h3>
                        <p class="text-[11px] text-slate-400">{{ __('Manage organization name and subdomain slug.') }}</p>
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

            {{-- Modal Body --}}
            <form wire:submit.prevent="saveWorkspaceSettings" class="p-6 space-y-4">
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
                            class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('tenantSlug') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all font-mono {{ \App\Support\Edition::isCloud() ? 'pr-28' : '' }}"
                            placeholder="acme"
                        >
                        @if (\App\Support\Edition::isCloud())
                            <div class="absolute right-3 text-xs font-mono text-slate-500 pointer-events-none">
                                .{{ \App\Support\Edition::baseDomain() }}
                            </div>
                        @endif
                    </div>

                    <div class="mt-1.5 flex items-center justify-between text-xs text-slate-400">
                        <span>{{ __('Live Preview:') }}</span>
                        <span class="font-mono text-indigo-400 font-medium">
                            @if (\App\Support\Edition::isCloud())
                                {{ strtolower(trim($tenantSlug ?: 'workspace')) }}.{{ \App\Support\Edition::baseDomain() }}
                            @else
                                /{{ strtolower(trim($tenantSlug ?: 'workspace')) }}
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

                {{-- Strict B2B Client Isolation Toggle --}}
                {{-- Strict B2B Client Isolation Toggle --}}
                <div class="pt-2 border-t border-slate-800/80">
                    <label class="flex items-start gap-3 p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 hover:border-slate-700 cursor-pointer transition-all">
                        <div class="relative inline-flex items-center mt-0.5 shrink-0">
                            <input
                                wire:model="strictClientIsolation"
                                type="checkbox"
                                class="sr-only peer"
                            >
                            <div class="w-10 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-white flex items-center gap-1.5">
                                <span>🔒</span>
                                <span>{{ __('Strict B2B Client Isolation') }}</span>
                            </span>
                            <span class="block text-[11px] text-slate-400 mt-1 leading-relaxed">
                                {{ __('When enabled, Client users can only view details of requests submitted by themselves or their own Facility/Organization. Other cards on public columns appear anonymized.') }}
                            </span>
                        </div>
                    </label>
                </div>

                {{-- Public Guest Drop-Box Toggle & Link --}}
                <div class="pt-2 border-t border-slate-800/80 space-y-3">
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
                                    @click="navigator.clipboard.writeText('{{ $this->publicDropBoxUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
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

                {{-- Modal Footer --}}
                <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-2.5">
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
