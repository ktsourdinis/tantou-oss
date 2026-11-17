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

        @if(\App\Support\Edition::isSelfHosted() && \App\Models\Tenant::count() === 0)
            <div class="mt-4 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                {{ __('Initial Organization Setup (1/1)') }}
            </div>
        @endif

        <h2 class="mt-4 text-2xl font-bold tracking-tight text-white">{{ __('Create a New Workspace') }}</h2>
        <p class="mt-2 text-sm text-slate-400">
            {{ __('Already have an organization workspace?') }}
            <a href="{{ route('login') }}" class="font-medium text-indigo-400 hover:text-indigo-300 underline underline-offset-4 transition-colors">
                {{ __('Sign In') }}
            </a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div class="bg-slate-900/80 backdrop-blur-xl py-8 px-6 sm:px-10 shadow-2xl rounded-3xl border border-slate-800/80">
            @if($linkSent)
                {{-- Step 1 Confirmation Screen --}}
                <div class="text-center space-y-4">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-2xl shadow-lg shadow-indigo-500/10">
                        📬
                    </div>

                    <h3 class="text-lg font-semibold text-white">
                        {{ __('Check your inbox!') }}
                    </h3>

                    <p class="text-sm text-slate-300 leading-relaxed">
                        {{ __('We sent a verification magic link valid for 30 minutes to') }}
                        <span class="font-medium text-indigo-400 block mt-1 font-mono text-xs">{{ $email }}</span>
                    </p>

                    <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800 text-xs text-slate-400 text-left space-y-1.5">
                        <div class="flex items-center gap-2 text-indigo-300 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                            <span>{{ __('Next step:') }}</span>
                        </div>
                        <p>{{ __('Click the link in your email to choose your organization name, subdomain slug, and admin password.') }}</p>
                    </div>

                    @if(!empty($devMagicLink))
                        <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-left">
                            <div class="flex items-center gap-1.5 text-amber-300 font-semibold mb-1">
                                <span>🛠️</span>
                                <span>{{ __('Local Dev Shortcut:') }}</span>
                            </div>
                            <a href="{{ $devMagicLink }}" class="text-indigo-400 hover:text-indigo-300 underline break-all text-[11px] font-mono">
                                {{ $devMagicLink }}
                            </a>
                        </div>
                    @endif

                    <div class="pt-2">
                        <button
                            type="button"
                            wire:click="changeEmail"
                            class="text-xs text-slate-400 hover:text-slate-200 underline underline-offset-4 cursor-pointer transition-colors"
                        >
                            {{ __('Send to a different email address') }}
                        </button>
                    </div>
                </div>
            @else
                {{-- Email Input Form --}}
                <form wire:submit="requestMagicLink" class="space-y-4">
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('Enter your work email to get started. We will send you a secure verification link to set up your organization workspace.') }}
                    </p>

                    {{-- Invisible Honeypot Field --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website_url">Website</label>
                        <input
                            wire:model="website_url"
                            id="website_url"
                            type="text"
                            tabindex="-1"
                            autocomplete="off"
                        >
                    </div>

                    {{-- Work Email --}}
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            {{ __('Work Email') }}
                        </label>
                        <input
                            wire:model="email"
                            id="email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="admin@company.com"
                            class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('email') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all font-sans"
                        >
                        @error('email')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 shadow-lg shadow-indigo-600/30 transition-all cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="requestMagicLink">{{ __('Send Verification Link') }}</span>
                            <span wire:loading wire:target="requestMagicLink" class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                {{ __('Sending Verification Link...') }}
                            </span>
                        </button>
                    </div>

                    <div class="text-center pt-2">
                        <p class="text-[11px] text-slate-500">
                            🔒 {{ __('Protected by magic-link email verification & rate limiting') }}
                        </p>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
