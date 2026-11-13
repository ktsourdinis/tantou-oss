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
            <form wire:submit="register" class="space-y-4">
                {{-- Organization Name --}}
                <div>
                    <label for="organizationName" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        {{ __('Organization / Team Name') }}
                    </label>
                    <input
                        wire:model.live.debounce.300ms="organizationName"
                        id="organizationName"
                        type="text"
                        required
                        placeholder="e.g. Acme Engineering or Hellenic Ops"
                        class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('organizationName') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                    >
                    @error('organizationName')
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
                        placeholder="admin@company.com"
                        class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('email') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                    >
                    @error('email')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        {{ __('Password (min 8 chars)') }}
                    </label>
                    <input
                        wire:model="password"
                        id="password"
                        type="password"
                        required
                        placeholder="••••••••"
                        class="block w-full rounded-xl bg-slate-950/70 border {{ $errors->has('password') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                    >
                    @error('password')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password Confirmation --}}
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        {{ __('Confirm Password') }}
                    </label>
                    <input
                        wire:model="password_confirmation"
                        id="password_confirmation"
                        type="password"
                        required
                        placeholder="••••••••"
                        class="block w-full rounded-xl bg-slate-950/70 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                    >
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 shadow-lg shadow-indigo-600/30 transition-all cursor-pointer"
                    >
                        <span wire:loading.remove wire:target="register">{{ __('Launch Organization Workspace') }}</span>
                        <span wire:loading wire:target="register" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            {{ __('Creating Workspace...') }}
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
