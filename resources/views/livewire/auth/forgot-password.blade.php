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

        <h2 class="mt-6 text-2xl font-bold tracking-tight text-white">{{ __('Reset Your Password') }}</h2>
        <p class="mt-2 text-xs text-slate-400">
            {{ __('Enter your registered work email and we will send you a secure link to reset your password.') }}
        </p>

        @if(app()->bound('currentTenant'))
            <div class="mt-2.5 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-medium bg-indigo-500/10 text-indigo-300 border border-indigo-500/20 shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                @if(\App\Support\Edition::usesSubdomains(request()))
                    <span>{{ app('currentTenant')->slug }}.{{ \App\Support\Edition::baseDomain() }}</span>
                @else
                    <span>{{ app('currentTenant')->name }} ({{ app('currentTenant')->slug }})</span>
                @endif
            </div>
        @endif
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div class="bg-slate-900/80 backdrop-blur-xl py-8 px-6 sm:px-10 shadow-2xl rounded-3xl border border-slate-800/80">
            @if ($linkSent)
                {{-- Success State --}}
                <div class="text-center space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 mx-auto flex items-center justify-center text-2xl shadow-inner">
                        ✉️
                    </div>

                    <h3 class="text-base font-semibold text-white">
                        {{ __('Check your inbox') }}
                    </h3>

                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('If an account exists for :email, we have sent a secure password reset link valid for 60 minutes.', ['email' => $email]) }}
                    </p>

                    @if(!empty($devResetLink))
                        <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-left space-y-1.5">
                            <div class="flex items-center gap-1.5 text-amber-300 font-semibold">
                                <span>🛠️</span>
                                <span>{{ __('Local Dev Reset Link:') }}</span>
                            </div>
                            <a href="{{ $devResetLink }}" class="text-indigo-400 hover:text-indigo-300 underline break-all text-[11px] font-mono block">
                                {{ $devResetLink }}
                            </a>
                        </div>
                    @endif

                    <div class="pt-2 flex flex-col gap-2.5">
                        <button
                            type="button"
                            wire:click="tryDifferentEmail"
                            class="text-xs text-slate-400 hover:text-slate-200 underline underline-offset-4 cursor-pointer transition-colors"
                        >
                            {{ __('Try a different email address') }}
                        </button>

                        <a
                            href="{{ route('login', request()->query()) }}"
                            class="text-xs text-indigo-400 hover:text-indigo-300 font-medium transition-colors"
                        >
                            &larr; {{ __('Return to sign in') }}
                        </a>
                    </div>
                </div>
            @else
                {{-- Form State --}}
                <form wire:submit="sendResetLink" class="space-y-5">
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            {{ __('Work Email') }}
                        </label>
                        <div class="relative">
                            <input
                                wire:model="email"
                                id="email"
                                type="email"
                                autocomplete="email"
                                required
                                autofocus
                                placeholder="you@company.com"
                                class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('email') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                            >
                        </div>
                        @error('email')
                            <p class="mt-1.5 text-xs text-rose-400 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 shadow-lg shadow-indigo-600/30 transition-all cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="sendResetLink">{{ __('Send Password Reset Link') }}</span>
                            <span wire:loading wire:target="sendResetLink" class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                <span>{{ __('Sending reset link...') }}</span>
                            </span>
                        </button>
                    </div>

                    <div class="text-center pt-2">
                        <a
                            href="{{ route('login', request()->query()) }}"
                            class="text-xs text-slate-400 hover:text-slate-200 transition-colors inline-flex items-center gap-1"
                        >
                            <span>&larr;</span>
                            <span>{{ __('Back to sign in') }}</span>
                        </a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
