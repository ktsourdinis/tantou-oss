<div class="min-h-screen bg-slate-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8 selection:bg-indigo-500 selection:text-white relative">
    <div class="absolute top-6 right-6">
        <x-language-switcher />
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <a href="{{ route('landing') }}" class="inline-flex items-center gap-3 group">
            <div class="h-12 w-12 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-amber-400 flex items-center justify-center font-bold text-xl text-white shadow-xl shadow-indigo-500/25 group-hover:scale-105 transition-transform">
                担
            </div>
            <div class="text-left">
                <span class="text-2xl font-bold tracking-tight text-white block">Tantou <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">担当</span></span>
                <span class="text-xs text-slate-400 block -mt-0.5">{{ __('Human Capacity OS') }}</span>
            </div>
        </a>

        <h2 class="mt-4 text-2xl font-bold tracking-tight text-white">{{ __('Complete Your Workspace') }}</h2>
        <p class="mt-1.5 text-xs text-slate-400">
            {{ __('Set up your organization name and administrator credentials') }}
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div class="bg-slate-900/80 backdrop-blur-xl py-8 px-6 sm:px-10 shadow-2xl rounded-3xl border border-slate-800/80">
            @if($isExpiredOrInvalid)
                {{-- Expired / Invalid Token Screen --}}
                <div class="text-center space-y-4">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-2xl shadow-lg shadow-rose-500/10">
                        ⚠️
                    </div>

                    <h3 class="text-lg font-semibold text-white">
                        {{ __('Invalid or Expired Link') }}
                    </h3>

                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('This registration link has expired or is invalid, and the temporary request was deleted. Please request a new link.') }}
                    </p>

                    <div class="pt-3">
                        <a
                            href="{{ route('register') }}"
                            class="inline-flex items-center justify-center gap-2 py-2.5 px-5 rounded-xl text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-md shadow-indigo-600/30 transition-all cursor-pointer"
                        >
                            <span>{{ __('Request a New Link') }}</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </a>
                    </div>
                </div>
            @else
                {{-- Verified Email Badge --}}
                <div class="mb-5 p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-between">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                        <div class="min-w-0">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-emerald-400 block">{{ __('Verified Email') }}</span>
                            <span class="text-xs text-white font-mono truncate block">{{ $email }}</span>
                        </div>
                    </div>
                    <span class="text-emerald-400 font-bold text-xs shrink-0">✓ {{ __('Verified') }}</span>
                </div>

                <form wire:submit="complete" class="space-y-4">
                    {{-- Organization Name --}}
                    <div>
                        <label for="organization_name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            {{ __('Organization / Workspace Name') }}
                        </label>
                        <input
                            wire:model.live.debounce.300ms="organization_name"
                            id="organization_name"
                            type="text"
                            required
                            placeholder="e.g. Acme Engineering or Hellenic Ops"
                            class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('organization_name') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                        >
                        @error('organization_name')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Editable Subdomain (Cloud Mode only) --}}
                    @if(\App\Support\Edition::isCloud())
                        <div>
                            <label for="slug" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                                {{ __('Workspace Subdomain') }}
                            </label>
                            <div class="relative flex items-center">
                                <input
                                    wire:model.live.debounce.300ms="slug"
                                    id="slug"
                                    type="text"
                                    required
                                    placeholder="acme"
                                    class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('slug') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all pr-28 font-mono"
                                >
                                <div class="absolute right-3 text-xs font-mono text-slate-500 pointer-events-none">
                                    .{{ \App\Support\Edition::baseDomain() }}
                                </div>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-xs text-slate-400">
                                <span>{{ __('Live Preview:') }}</span>
                                <span class="font-mono text-indigo-400 font-medium">
                                    {{ strtolower(trim($slug ?: 'workspace')) }}.{{ \App\Support\Edition::baseDomain() }}
                                </span>
                            </div>
                            @error('slug')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror

                            @if(!empty($suggestedSlugs))
                                <div class="mt-2 p-2.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs">
                                    <p class="text-amber-300 font-medium text-xs mb-1.5 flex items-center gap-1.5">
                                        <span>⚠️</span>
                                        <span>{{ __('This subdomain is already taken. Available suggestions:') }}</span>
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($suggestedSlugs as $suggested)
                                            <button
                                                type="button"
                                                wire:click="selectSuggestedSlug('{{ $suggested }}')"
                                                class="px-2.5 py-1 rounded-xl bg-slate-950/80 border border-indigo-500/30 hover:border-indigo-500 text-indigo-300 hover:text-white font-mono text-[11px] transition-all cursor-pointer shadow-sm hover:scale-105"
                                                title="{{ __('Click to select this subdomain') }}"
                                            >
                                                {{ $suggested }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Admin Full Name --}}
                    <div>
                        <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            {{ __('Your Full Name (Admin)') }}
                        </label>
                        <input
                            wire:model="name"
                            id="name"
                            type="text"
                            required
                            placeholder="Alexandros Papadopoulos"
                            class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('name') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                        >
                        @error('name')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password with Alpine Toggle and Checklist Hint --}}
                    <div x-data="{ showPass: false }">
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                                {{ __('Admin Password') }}
                            </label>
                            <button
                                type="button"
                                @click="showPass = !showPass"
                                class="text-xs text-slate-400 hover:text-slate-200 transition-colors focus:outline-none flex items-center gap-1 cursor-pointer"
                                :title="showPass ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                            >
                                <span x-show="!showPass" class="text-xs">👁️ {{ __('Show') }}</span>
                                <span x-show="showPass" x-cloak class="text-xs">🙈 {{ __('Hide') }}</span>
                            </button>
                        </div>
                        <div class="relative">
                            <input
                                wire:model="password"
                                id="password"
                                :type="showPass ? 'text' : 'password'"
                                required
                                placeholder="••••••••••••"
                                class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('password') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all font-mono"
                            >
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400 flex items-center gap-1">
                            <span>🔒</span>
                            <span>{{ __('Min 10 chars • Upper & Lowercase • Number • Symbol') }}</span>
                        </p>
                        @error('password')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password Confirmation --}}
                    <div x-data="{ showPassConf: false }">
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password_confirmation" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                                {{ __('Confirm Password') }}
                            </label>
                            <button
                                type="button"
                                @click="showPassConf = !showPassConf"
                                class="text-xs text-slate-400 hover:text-slate-200 transition-colors focus:outline-none flex items-center gap-1 cursor-pointer"
                                :title="showPassConf ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                            >
                                <span x-show="!showPassConf" class="text-xs">👁️ {{ __('Show') }}</span>
                                <span x-show="showPassConf" x-cloak class="text-xs">🙈 {{ __('Hide') }}</span>
                            </button>
                        </div>
                        <div class="relative">
                            <input
                                wire:model="password_confirmation"
                                id="password_confirmation"
                                :type="showPassConf ? 'text' : 'password'"
                                required
                                placeholder="••••••••••••"
                                class="block w-full rounded-xl bg-slate-950/70 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all font-mono"
                            >
                        </div>
                    </div>

                    {{-- Plan Selection (Single Self-Service Tantou Cloud Managed Plan + Enterprise Callout) --}}
                    @if(\App\Support\Edition::isCloud())
                        <div class="pt-1 space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                                    {{ __('Select Your Cloud Plan') }}
                                </label>
                                <div class="inline-flex rounded-xl bg-slate-950 border border-slate-800 p-0.5 text-[11px]">
                                    <button
                                        type="button"
                                        wire:click="$set('billing_interval', 'monthly')"
                                        class="px-2.5 py-1 rounded-lg font-medium transition-all cursor-pointer {{ $billing_interval === 'monthly' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}"
                                    >
                                        {{ __('Monthly') }} (€8)
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('billing_interval', 'yearly')"
                                        class="px-2.5 py-1 rounded-lg font-medium transition-all cursor-pointer {{ $billing_interval === 'yearly' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}"
                                    >
                                        {{ __('Yearly') }} (€7)
                                    </button>
                                </div>
                            </div>

                            {{-- Sole Self-Service Plan: Tantou Cloud Managed --}}
                            <div class="w-full p-4 rounded-2xl border bg-indigo-600/15 border-indigo-500 ring-2 ring-indigo-500/30 text-left relative">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-bold text-white">{{ __('Tantou Cloud Managed') }}</span>
                                    <span class="text-xs font-bold text-indigo-400">
                                        {{ $billing_interval === 'yearly' ? '€7' : '€8' }}<span class="text-[10px] text-slate-400 font-normal"> / {{ __('active worker / month') }}</span>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-300 leading-tight">
                                    {{ __('Zero-maintenance EU GDPR Cloud Hosting with managed Reverb WebSockets, daily backups & strict B2B isolation.') }}
                                </p>
                                <div class="mt-2.5 flex items-center justify-between text-[10px] font-semibold">
                                    <span class="text-indigo-300">€7 / {{ __('mo billed annually') }}</span>
                                    <span class="text-emerald-400 flex items-center gap-1"><span>✨</span><span>{{ __('14-day free trial') }}</span></span>
                                </div>
                            </div>

                            {{-- Compact Secondary Callout for Public Sector & Enterprise --}}
                            <div class="p-3 rounded-2xl bg-purple-950/20 border border-purple-500/30 text-xs flex flex-col gap-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-purple-300">{{ __('Public Sector & Enterprise (White-Label / On-Premise / SLA Contract)') }}</span>
                                    <span class="text-[10px] font-semibold text-purple-400">{{ __('From') }} €3,600 / {{ __('year') }}</span>
                                </div>
                                <a
                                    href="mailto:tantou@tsourdinis.info?subject=Tantou%20Enterprise%20White-Label%20Inquiry"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-purple-300 hover:text-white underline underline-offset-4 transition-colors"
                                >
                                    <span>{{ __('Need White-Label or Public Sector Procurement? Contact tantou@tsourdinis.info →') }}</span>
                                </a>
                            </div>

                            @error('plan')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 shadow-lg shadow-indigo-600/30 transition-all cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="complete">{{ __('Launch Organization Workspace') }}</span>
                            <span wire:loading wire:target="complete" class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                {{ __('Creating Workspace...') }}
                            </span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
