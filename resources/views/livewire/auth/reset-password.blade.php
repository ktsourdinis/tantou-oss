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

        <h2 class="mt-6 text-2xl font-bold tracking-tight text-white">{{ __('Choose a New Password') }}</h2>
        <p class="mt-2 text-xs text-slate-400">
            {{ __('Please enter your work email and choose a strong new password for your account.') }}
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div class="bg-slate-900/80 backdrop-blur-xl py-8 px-6 sm:px-10 shadow-2xl rounded-3xl border border-slate-800/80">
            <form wire:submit="resetPassword" class="space-y-5">
                {{-- Email Field --}}
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

                {{-- New Password --}}
                <div x-data="{ showPass: false }">
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                            {{ __('New Password') }}
                        </label>
                        <button
                            type="button"
                            @click="showPass = !showPass"
                            class="text-xs text-slate-400 hover:text-slate-200 transition-colors focus:outline-none flex items-center gap-1 cursor-pointer"
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
                            autocomplete="new-password"
                            required
                            placeholder="••••••••••••"
                            class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('password') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                        >
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-500">
                        {{ __('Minimum 10 characters with uppercase, lowercase, numbers & symbols.') }}
                    </p>
                    @error('password')
                        <p class="mt-1.5 text-xs text-rose-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Confirm Password --}}
                <div x-data="{ showConfirm: false }">
                    <div class="flex items-center justify-between mb-2">
                        <label for="password_confirmation" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                            {{ __('Confirm Password') }}
                        </label>
                        <button
                            type="button"
                            @click="showConfirm = !showConfirm"
                            class="text-xs text-slate-400 hover:text-slate-200 transition-colors focus:outline-none flex items-center gap-1 cursor-pointer"
                        >
                            <span x-show="!showConfirm" class="text-xs">👁️ {{ __('Show') }}</span>
                            <span x-show="showConfirm" x-cloak class="text-xs">🙈 {{ __('Hide') }}</span>
                        </button>
                    </div>
                    <div class="relative">
                        <input
                            wire:model="password_confirmation"
                            id="password_confirmation"
                            :type="showConfirm ? 'text' : 'password'"
                            autocomplete="new-password"
                            required
                            placeholder="••••••••••••"
                            class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('password_confirmation') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                        >
                    </div>
                    @error('password_confirmation')
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
                        <span wire:loading.remove wire:target="resetPassword">{{ __('Reset Password & Sign In') }}</span>
                        <span wire:loading wire:target="resetPassword" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>{{ __('Updating password...') }}</span>
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
        </div>
    </div>
</div>
