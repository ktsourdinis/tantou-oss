<div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans selection:bg-indigo-500 selection:text-white">
    {{-- Top Header / Navigation Bar --}}
    <header class="border-b border-slate-800/80 bg-slate-900/80 backdrop-blur-xl sticky top-0 z-30 px-4 sm:px-6 py-3.5">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <a href="{{ route('board') }}" class="h-10 w-10 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-amber-400 flex items-center justify-center font-bold text-lg text-white shadow-lg shadow-indigo-500/20 hover:scale-105 transition-transform shrink-0">
                    担
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg sm:text-xl font-bold tracking-tight text-white">Tantou <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30">Control Plane</span></h1>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            <span>🛡️</span>
                            <span>{{ __('Superadmin') }}</span>
                        </span>
                    </div>
                    <p class="text-xs text-slate-400">{{ __('Platform Overview, Multi-Tenant Governance & Workspace Impersonation') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                <a
                    href="{{ route('board') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-xs font-medium text-slate-200 transition-all cursor-pointer shadow-sm"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    <span>{{ __('Back to Board') }}</span>
                </a>

                <x-language-switcher />

                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-800/70 border border-slate-700/60 text-xs">
                    <div class="h-6 w-6 rounded-lg bg-amber-500/20 text-amber-300 font-bold flex items-center justify-center text-xs shrink-0">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <span class="font-semibold text-slate-200 truncate max-w-[120px]">{{ auth()->user()->name }}</span>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition-all cursor-pointer"
                        title="{{ __('Sign Out') }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-8 space-y-8">
        {{-- Flash Messages --}}
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-500/40 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-200 flex items-center gap-3 backdrop-blur-md shadow-lg">
                <span class="text-lg">✓</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-500/40 bg-rose-950/40 px-4 py-3 text-sm text-rose-200 flex items-center gap-3 backdrop-blur-md shadow-lg">
                <span class="text-lg">⚠️</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Top Platform KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            {{-- KPI 1: Workspaces --}}
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/60 backdrop-blur-xl p-5 shadow-xl relative overflow-hidden group hover:border-slate-700/80 transition-all">
                <div class="absolute -top-12 -right-12 w-28 h-28 bg-indigo-500/10 rounded-full blur-2xl group-hover:bg-indigo-500/20 transition-all"></div>
                <div class="flex items-center justify-between text-xs font-semibold text-slate-400 mb-2">
                    <span>{{ __('Total Workspaces') }}</span>
                    <span class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">🏢</span>
                </div>
                <div class="text-3xl font-bold tracking-tight text-white mb-2">{{ $totalTenants }}</div>
                <div class="flex items-center gap-2 text-xs text-slate-400 flex-wrap">
                    <span class="inline-flex items-center gap-1 text-emerald-400 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        {{ $activeTenants }} {{ __('Active') }}
                    </span>
                    <span class="text-slate-600">•</span>
                    <span class="text-amber-400">{{ $trialingTenants }} {{ __('Trialing') }}</span>
                    @if ($suspendedTenants > 0)
                        <span class="text-slate-600">•</span>
                        <span class="text-rose-400">{{ $suspendedTenants }} {{ __('Suspended') }}</span>
                    @endif
                </div>
            </div>

            {{-- KPI 2: MRR & Plans Breakdown --}}
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/60 backdrop-blur-xl p-5 shadow-xl relative overflow-hidden group hover:border-slate-700/80 transition-all">
                <div class="absolute -top-12 -right-12 w-28 h-28 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all"></div>
                <div class="flex items-center justify-between text-xs font-semibold text-slate-400 mb-2">
                    <span>{{ __('Subscription Tiers & MRR') }}</span>
                    <span class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">💳</span>
                </div>
                <div class="flex items-baseline gap-2 mb-2">
                    <span class="text-3xl font-bold tracking-tight text-white">€{{ number_format($estimatedMrr, 0) }}</span>
                    <span class="text-xs font-semibold text-emerald-400 bg-emerald-500/15 border border-emerald-500/25 px-2 py-0.5 rounded-full">
                        {{ __('est. MRR') }}
                    </span>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span class="text-indigo-300 font-medium">{{ $businessTenants }} {{ __('Business (€79)') }}</span>
                    <span class="text-slate-600">•</span>
                    <span class="text-slate-300">{{ $teamTenants }} {{ __('Team (€29)') }}</span>
                </div>
            </div>

            {{-- KPI 3: Platform Users --}}
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/60 backdrop-blur-xl p-5 shadow-xl relative overflow-hidden group hover:border-slate-700/80 transition-all">
                <div class="absolute -top-12 -right-12 w-28 h-28 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-all"></div>
                <div class="flex items-center justify-between text-xs font-semibold text-slate-400 mb-2">
                    <span>{{ __('Platform Users') }}</span>
                    <span class="p-1.5 rounded-lg bg-blue-500/10 text-blue-400 border border-blue-500/20">👥</span>
                </div>
                <div class="text-3xl font-bold tracking-tight text-white mb-2">{{ $totalUsers }}</div>
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span class="text-blue-300 font-medium">{{ $workerUsers }} {{ __('Workers / Leads') }}</span>
                    <span class="text-slate-600">•</span>
                    <span class="text-slate-400">{{ $clientUsers }} {{ __('Clients') }}</span>
                </div>
            </div>

            {{-- KPI 4: Post-Its Processed --}}
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/60 backdrop-blur-xl p-5 shadow-xl relative overflow-hidden group hover:border-slate-700/80 transition-all">
                <div class="absolute -top-12 -right-12 w-28 h-28 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-all"></div>
                <div class="flex items-center justify-between text-xs font-semibold text-slate-400 mb-2">
                    <span>{{ __('Total Post-Its Processed') }}</span>
                    <span class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20">📋</span>
                </div>
                <div class="text-3xl font-bold tracking-tight text-white mb-2">{{ $totalPostIts }}</div>
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span class="text-amber-300 font-medium">{{ $activePostIts }} {{ __('Active') }}</span>
                    <span class="text-slate-600">•</span>
                    <span class="text-emerald-400 font-medium">{{ $donePostIts }} {{ __('Completed') }}</span>
                </div>
            </div>
        </div>

        {{-- Filter & Actions Toolbar --}}
        <div class="rounded-2xl border border-slate-800/80 bg-slate-900/50 backdrop-blur-xl p-4 shadow-xl flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-1">
                {{-- Search Box --}}
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </span>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="{{ __('Search by organization, slug or custom domain...') }}"
                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                    >
                </div>

                {{-- Plan Filter --}}
                <select
                    wire:model.live="planFilter"
                    class="py-2 px-3 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="">{{ __('All Plans') }}</option>
                    <option value="team">{{ __('Team') }}</option>
                    <option value="business">{{ __('Business') }}</option>
                </select>

                {{-- Status Filter --}}
                <select
                    wire:model.live="statusFilter"
                    class="py-2 px-3 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="trialing">{{ __('Trialing') }}</option>
                    <option value="past_due">{{ __('Past Due') }}</option>
                    <option value="suspended">{{ __('Suspended') }}</option>
                </select>
            </div>

            {{-- Create Tenant Action --}}
            <button
                wire:click="openCreateTenantModal"
                type="button"
                class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-lg shadow-indigo-600/30 transition-all cursor-pointer shrink-0"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>{{ __('New Workspace') }}</span>
            </button>
        </div>

        {{-- All Tenants Management Table --}}
        <div class="rounded-2xl border border-slate-800/80 bg-slate-900/60 backdrop-blur-xl shadow-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800/80 bg-slate-950/60 text-slate-400 font-semibold uppercase tracking-wider text-[11px]">
                            <th class="py-3.5 px-4 sm:px-6">{{ __('Organization / Slug') }}</th>
                            <th class="py-3.5 px-4">{{ __('Custom Domain') }}</th>
                            <th class="py-3.5 px-4">{{ __('Plan & Status') }}</th>
                            <th class="py-3.5 px-4">{{ __('Workers') }}</th>
                            <th class="py-3.5 px-4">{{ __('Clients') }}</th>
                            <th class="py-3.5 px-4">{{ __('Tasks') }}</th>
                            <th class="py-3.5 px-4">{{ __('Telegram') }}</th>
                            <th class="py-3.5 px-4">{{ __('Created') }}</th>
                            <th class="py-3.5 px-4 sm:px-6 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-sans">
                        @forelse ($tenants as $tenant)
                            @php
                                $workspaceUrl = \App\Support\Edition::workspaceUrl($tenant, '/board');
                                $maxLimit = $tenant->maxWorkersLimit();
                                $workersDisplay = $tenant->workers_count . ' / ' . ($maxLimit !== null ? $maxLimit : '∞');
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition-colors group">
                                {{-- Org & Slug Link --}}
                                <td class="py-4 px-4 sm:px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-slate-800 border border-slate-700/60 flex items-center justify-center font-bold text-sm text-indigo-400 shrink-0">
                                            {{ substr($tenant->name, 0, 1) }}
                                        </div>
                                        <div class="min-w-0">
                                            <a
                                                href="{{ $workspaceUrl }}"
                                                target="_blank"
                                                class="font-semibold text-white hover:text-indigo-400 transition-colors flex items-center gap-1.5"
                                                title="{{ __('Open Workspace Board') }}"
                                            >
                                                <span class="truncate max-w-[200px]">{{ $tenant->name }}</span>
                                                <svg class="w-3.5 h-3.5 text-slate-500 group-hover:text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                            </a>
                                            <div class="font-mono text-[11px] text-slate-400 truncate">
                                                ?workspace={{ $tenant->slug }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Custom Domain --}}
                                <td class="py-4 px-4">
                                    @if (filled($tenant->custom_domain))
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-500/10 text-indigo-300 border border-indigo-500/20 font-mono text-[11px]">
                                            <span>🌐</span>
                                            <span class="truncate max-w-[140px]">{{ $tenant->custom_domain }}</span>
                                        </span>
                                    @else
                                        <span class="text-slate-500 font-mono text-xs">—</span>
                                    @endif
                                </td>

                                {{-- Plan & Status --}}
                                <td class="py-4 px-4">
                                    <div class="flex flex-col gap-1 items-start">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md font-semibold text-[10px] uppercase tracking-wide {{ $tenant->plan === 'business' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-blue-500/20 text-blue-300 border border-blue-500/30' }}">
                                            {{ $tenant->plan ?: 'team' }}
                                        </span>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium capitalize {{ $tenant->subscription_status === 'active' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25' : ($tenant->subscription_status === 'trialing' ? 'bg-amber-500/15 text-amber-300 border border-amber-500/25' : 'bg-rose-500/15 text-rose-400 border border-rose-500/25') }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $tenant->subscription_status === 'active' ? 'bg-emerald-400' : ($tenant->subscription_status === 'trialing' ? 'bg-amber-400' : 'bg-rose-400') }}"></span>
                                            <span>{{ $tenant->subscription_status ?: 'active' }}</span>
                                        </span>
                                    </div>
                                </td>

                                {{-- Workers Count --}}
                                <td class="py-4 px-4">
                                    <span class="font-mono text-xs font-semibold text-slate-200">{{ $workersDisplay }}</span>
                                </td>

                                {{-- Clients Count --}}
                                <td class="py-4 px-4">
                                    <span class="font-mono text-xs text-slate-300">{{ $tenant->clients_count }}</span>
                                </td>

                                {{-- Tasks Count --}}
                                <td class="py-4 px-4">
                                    <span class="font-mono text-xs text-indigo-300 font-semibold">{{ $tenant->total_tasks_count }}</span>
                                </td>

                                {{-- Telegram Bot Status --}}
                                <td class="py-4 px-4">
                                    @if ($tenant->telegram_enabled)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-500/15 text-sky-300 border border-sky-500/25 text-[11px] font-medium">
                                            <span>✈️</span>
                                            <span>{{ __('Active') }}</span>
                                        </span>
                                    @else
                                        <span class="text-slate-500 text-[11px]">{{ __('Off') }}</span>
                                    @endif
                                </td>

                                {{-- Created Date --}}
                                <td class="py-4 px-4 text-slate-400 whitespace-nowrap text-[11px]">
                                    {{ $tenant->created_at?->format('M d, Y') ?? '—' }}
                                </td>

                                {{-- Actions --}}
                                <td class="py-4 px-4 sm:px-6 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        {{-- Edit Tenant Button --}}
                                        <button
                                            wire:click="openEditTenantModal({{ $tenant->id }})"
                                            type="button"
                                            class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium border border-slate-700/60 transition-colors cursor-pointer"
                                            title="{{ __('Edit Workspace Settings & Plan') }}"
                                        >
                                            <span>⚙️ {{ __('Edit') }}</span>
                                        </button>

                                        {{-- Impersonate Admin Button --}}
                                        <button
                                            wire:click="impersonate({{ $tenant->id }})"
                                            wire:loading.attr="disabled"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 text-xs font-semibold transition-all cursor-pointer"
                                            title="{{ __('Log in as Workspace Admin') }}"
                                        >
                                            <span>🛡️</span>
                                            <span>{{ __('Login as Admin') }}</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <span class="text-3xl">🔍</span>
                                        <p class="text-sm">{{ __('No organization workspaces match your search criteria.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Links --}}
            @if ($tenants->hasPages())
                <div class="px-6 py-4 border-t border-slate-800/80 bg-slate-950/40">
                    {{ $tenants->links() }}
                </div>
            @endif
        </div>
    </main>

    {{-- Edit Tenant Modal --}}
    @if ($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-in fade-in duration-150">
            <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>⚙️</span>
                        <span>{{ __('Edit Workspace: :name', ['name' => $editingTenantName]) }}</span>
                    </h3>
                    <button wire:click="closeEditModal" type="button" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveTenantEdits" class="space-y-4">
                    {{-- Plan Selection --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Subscription Plan') }}</label>
                        <select wire:model="editingPlan" class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="team">{{ __('Team Plan (€29 / month, 15 workers, 3 depts)') }}</option>
                            <option value="business">{{ __('Business Plan (€79 / month, unlimited workers & depts)') }}</option>
                        </select>
                        @error('editingPlan') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Subscription Status --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Subscription Status') }}</label>
                        <select wire:model="editingStatus" class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="active">{{ __('Active') }}</option>
                            <option value="trialing">{{ __('Trialing') }}</option>
                            <option value="past_due">{{ __('Past Due') }}</option>
                            <option value="suspended">{{ __('Suspended') }}</option>
                        </select>
                        @error('editingStatus') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Custom Domain --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Custom Domain (e.g. app.mycompany.gr)') }}</label>
                        <input
                            wire:model="editingCustomDomain"
                            type="text"
                            placeholder="app.example.com"
                            class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                        <p class="text-[11px] text-slate-500 mt-1">{{ __('Leave blank to use default workspace subdomain or path routing.') }}</p>
                        @error('editingCustomDomain') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                        <button
                            wire:click="closeEditModal"
                            type="button"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium cursor-pointer"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 cursor-pointer"
                        >
                            {{ __('Save Changes') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Create Tenant Modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-in fade-in duration-150">
            <div class="w-full max-w-lg rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>🏢</span>
                        <span>{{ __('Provision New Organization Workspace') }}</span>
                    </h3>
                    <button wire:click="closeCreateModal" type="button" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <form wire:submit="createTenant" class="space-y-4">
                    {{-- Organization Name & Slug --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Organization Name') }}</label>
                            <input
                                wire:model.live.debounce.300ms="newTenantName"
                                type="text"
                                placeholder="{{ __('Acme Corp') }}"
                                class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                            @error('newTenantName') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Subdomain / Slug') }}</label>
                            <input
                                wire:model="newTenantSlug"
                                type="text"
                                placeholder="acme"
                                class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                            @error('newTenantSlug') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Plan & Status --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Plan') }}</label>
                            <select wire:model="newTenantPlan" class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="team">{{ __('Team (€29)') }}</option>
                                <option value="business">{{ __('Business (€79)') }}</option>
                            </select>
                            @error('newTenantPlan') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Status') }}</label>
                            <select wire:model="newTenantStatus" class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="active">{{ __('Active') }}</option>
                                <option value="trialing">{{ __('Trialing (14-day)') }}</option>
                                <option value="suspended">{{ __('Suspended') }}</option>
                            </select>
                            @error('newTenantStatus') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Initial Workspace Admin --}}
                    <div class="pt-2 border-t border-slate-800/80">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">{{ __('Initial Admin Account') }}</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Admin Full Name') }}</label>
                                <input
                                    wire:model="newAdminName"
                                    type="text"
                                    placeholder="{{ __('Alex Papadopoulos') }}"
                                    class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                                @error('newAdminName') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Admin Email Address') }}</label>
                                <input
                                    wire:model="newAdminEmail"
                                    type="email"
                                    placeholder="admin@example.com"
                                    class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                                @error('newAdminEmail') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Password') }}</label>
                                    <input
                                        wire:model="newAdminPassword"
                                        type="password"
                                        placeholder="••••••••••••"
                                        class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    >
                                    @error('newAdminPassword') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ __('Confirm Password') }}</label>
                                    <input
                                        wire:model="newAdminPassword_confirmation"
                                        type="password"
                                        placeholder="••••••••••••"
                                        class="w-full py-2 px-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <button
                            wire:click="closeCreateModal"
                            type="button"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium cursor-pointer"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 cursor-pointer"
                        >
                            {{ __('Provision Workspace') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
