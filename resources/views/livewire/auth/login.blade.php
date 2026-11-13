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
        <h2 class="mt-6 text-2xl font-bold tracking-tight text-white">{{ __('Sign In to Your Workspace') }}</h2>
        <p class="mt-2 text-sm text-slate-400">
            {{ __('Or') }}
            <a href="{{ route('register') }}" class="font-medium text-indigo-400 hover:text-indigo-300 underline underline-offset-4 transition-colors">
                {{ __('create a new organization workspace') }}
            </a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div class="bg-slate-900/80 backdrop-blur-xl py-8 px-6 sm:px-10 shadow-2xl rounded-3xl border border-slate-800/80">
            <form wire:submit="authenticate" class="space-y-5">
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

                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        {{ __('Password') }}
                    </label>
                    <div class="relative">
                        <input
                            wire:model="password"
                            id="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                            class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('password') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                        >
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-xs text-rose-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            wire:model="remember"
                            type="checkbox"
                            class="w-4 h-4 rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500/20 focus:ring-offset-0"
                        >
                        <span class="text-xs text-slate-400">{{ __('Remember me') }}</span>
                    </label>
                </div>

                <div>
                    <button
                        type="submit"
                        class="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 shadow-lg shadow-indigo-600/30 transition-all cursor-pointer"
                    >
                        <span wire:loading.remove wire:target="authenticate">{{ __('Sign In') }}</span>
                        <span wire:loading wire:target="authenticate" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            {{ __('Authenticating...') }}
                        </span>
                    </button>
                </div>
            </form>

            @if (app()->environment('local'))
                <div class="mt-8 pt-6 border-t border-slate-800/80">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            {{ __('Quick Demo Login') }}
                        </span>
                        <span class="text-[10px] text-slate-500 font-mono">{{ __('local dev mode') }}</span>
                    </div>
                    <p class="text-xs text-slate-400 mb-3">
                        {{ __('Instantly test the board through different organizational roles:') }}
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        {{-- Admin --}}
                        <button
                            wire:click="quickLogin('admin@tantou.local')"
                            type="button"
                            class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-950/60 hover:bg-indigo-950/40 border border-slate-800 hover:border-indigo-700/50 text-left transition-all cursor-pointer group"
                        >
                            <div class="h-8 w-8 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                                🛡️
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-slate-200 group-hover:text-white truncate">Alexandros</div>
                                <div class="text-[10px] text-amber-400/90 font-medium">{{ __('Tenant Admin') }}</div>
                            </div>
                        </button>

                        {{-- Worker Nikos --}}
                        <button
                            wire:click="quickLogin('nikos@tantou.local')"
                            type="button"
                            class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-950/60 hover:bg-indigo-950/40 border border-slate-800 hover:border-indigo-700/50 text-left transition-all cursor-pointer group"
                        >
                            <div class="h-8 w-8 rounded-lg bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                                ⚡
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-slate-200 group-hover:text-white truncate">Nikos Pappas</div>
                                <div class="text-[10px] text-rose-400/90 font-medium">{{ __('Worker') }} (9/10 {{ __('Cap') }})</div>
                            </div>
                        </button>

                        {{-- Worker Elena --}}
                        <button
                            wire:click="quickLogin('elena@tantou.local')"
                            type="button"
                            class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-950/60 hover:bg-indigo-950/40 border border-slate-800 hover:border-indigo-700/50 text-left transition-all cursor-pointer group"
                        >
                            <div class="h-8 w-8 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                                ⚡
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-slate-200 group-hover:text-white truncate">Elena Dimitriou</div>
                                <div class="text-[10px] text-emerald-400/90 font-medium">{{ __('Worker') }} (5/10 {{ __('Cap') }})</div>
                            </div>
                        </button>

                        {{-- Client Sofia --}}
                        <button
                            wire:click="quickLogin('client@tantou.local')"
                            type="button"
                            class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-950/60 hover:bg-indigo-950/40 border border-slate-800 hover:border-indigo-700/50 text-left transition-all cursor-pointer group"
                        >
                            <div class="h-8 w-8 rounded-lg bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                                💼
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-slate-200 group-hover:text-white truncate">Sofia Client</div>
                                <div class="text-[10px] text-blue-400/90 font-medium">{{ __('Client / Requester') }}</div>
                            </div>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
