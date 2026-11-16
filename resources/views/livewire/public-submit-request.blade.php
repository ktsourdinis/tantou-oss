<div class="min-h-screen bg-slate-950 py-10 px-4 sm:px-6 lg:px-8 selection:bg-indigo-500 selection:text-white">
    <div class="max-w-2xl mx-auto space-y-8">
        {{-- Header & Organization Branding --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-400 flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-indigo-600/30">
                    担
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                        <span>{{ $tenant->name }}</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">
                            {{ __('Drop-Box') }}
                        </span>
                    </h1>
                    <p class="text-xs text-slate-400">{{ __('Public Request Submission Portal') }}</p>
                </div>
            </div>

            {{-- Locale Switcher --}}
            <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-900 border border-slate-800 text-xs">
                <form action="{{ route('locale.switch', ['locale' => 'el']) }}" method="POST" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all {{ app()->getLocale() === 'el' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white' }}"
                    >
                        ΕΛ
                    </button>
                </form>
                <form action="{{ route('locale.switch', ['locale' => 'en']) }}" method="POST" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white' }}"
                    >
                        EN
                    </button>
                </form>
            </div>
        </div>

        {{-- Intro Card --}}
        <div class="p-6 rounded-3xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-sm space-y-3 relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-indigo-500/5 rounded-full blur-2xl pointer-events-none"></div>

            <div class="flex items-start gap-4">
                <div class="p-3 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-2xl shrink-0">
                    📥
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">{{ __('Submit a Service Request') }}</h2>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        {{ __('No user account needed. Your request will be placed directly in our team queue and you will receive a real-time live tracking link.') }}
                    </p>
                </div>
            </div>

            @if (request()->has('facility') && filled(request()->query('facility')))
                <div class="mt-2 p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center gap-2 text-xs text-indigo-300">
                    <span>📍</span>
                    <span>{{ __('Origin Unit:') }} <strong class="text-white">{{ $facility_name }}</strong></span>
                </div>
            @endif
        </div>

        {{-- Submission Form --}}
        <form wire:submit.prevent="submit" class="p-6 sm:p-8 rounded-3xl bg-slate-900 border border-slate-800 shadow-2xl space-y-6">
            {{-- Name & Contact Row --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="guest_name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        {{ __('Your Full Name') }} <span class="text-rose-400">*</span>
                    </label>
                    <input
                        wire:model="guest_name"
                        id="guest_name"
                        type="text"
                        required
                        placeholder="{{ __('e.g. Maria Papadopoulou') }}"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-950/70 border {{ $errors->has('guest_name') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                    >
                    @error('guest_name')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="guest_contact" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        {{ __('Phone / Extension / Email') }}
                    </label>
                    <input
                        wire:model="guest_contact"
                        id="guest_contact"
                        type="text"
                        placeholder="{{ __('e.g. Ext. 2140 or maria@example.com') }}"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-950/70 border {{ $errors->has('guest_contact') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                    >
                    @error('guest_contact')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Facility & Department Row --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="facility_name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        {{ __('Facility / Department / Unit') }} <span class="text-rose-400">*</span>
                    </label>
                    <input
                        wire:model="facility_name"
                        id="facility_name"
                        type="text"
                        required
                        placeholder="{{ __('e.g. Radiology, Floor 3, Branch B') }}"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-950/70 border {{ $errors->has('facility_name') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                    >
                    @error('facility_name')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="department_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        {{ __('Target Service Department') }} <span class="text-rose-400">*</span>
                    </label>
                    <select
                        wire:model="department_id"
                        id="department_id"
                        required
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-950/70 border {{ $errors->has('department_id') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} text-sm text-white focus:outline-none focus:ring-4 transition-all cursor-pointer"
                    >
                        <option value="">{{ __('Select Department...') }}</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    @error('department_id')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Title --}}
            <div>
                <label for="title" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    {{ __('Brief Issue Summary') }} <span class="text-rose-400">*</span>
                </label>
                <input
                    wire:model="title"
                    id="title"
                    type="text"
                    required
                    maxlength="160"
                    placeholder="{{ __('e.g. Printer offline in room 204') }}"
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-950/70 border {{ $errors->has('title') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all"
                >
                @error('title')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Description --}}
            <div>
                <label for="description" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    {{ __('Details / Description') }}
                </label>
                <textarea
                    wire:model="description"
                    id="description"
                    rows="4"
                    maxlength="2000"
                    placeholder="{{ __('Please describe the issue or request with any pertinent details...') }}"
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-950/70 border {{ $errors->has('description') ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20' }} text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-4 transition-all resize-none"
                ></textarea>
                @error('description')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Attachments Upload --}}
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    {{ __('Attachments (Optional, max 2 files, 10MB each)') }}
                </label>
                <div class="relative border-2 border-dashed border-slate-800 hover:border-slate-700 rounded-2xl p-4 text-center transition-all bg-slate-950/40">
                    <input
                        type="file"
                        wire:model="attachments"
                        id="attachments"
                        multiple
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                    >
                    <div class="flex flex-col items-center justify-center pointer-events-none py-2">
                        <span class="text-2xl mb-1">📎</span>
                        <p class="text-xs text-slate-300 font-medium">
                            {{ __('Click or drag files here to upload') }}
                        </p>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            {{ __('Images, PDF, Word, Excel up to 10MB each') }}
                        </p>
                    </div>
                </div>

                <div wire:loading wire:target="attachments" class="mt-2 text-xs text-indigo-400 flex items-center gap-2">
                    <svg class="animate-spin h-3.5 w-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    <span>{{ __('Uploading files...') }}</span>
                </div>

                @if (! empty($attachments))
                    <div class="mt-3 space-y-2">
                        @foreach ($attachments as $index => $file)
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-slate-300">
                                <span class="truncate max-w-[280px] font-mono">{{ $file->getClientOriginalName() }}</span>
                                <button
                                    type="button"
                                    wire:click="removeAttachment({{ $index }})"
                                    class="text-rose-400 hover:text-rose-300 p-1 cursor-pointer transition-colors"
                                    title="{{ __('Remove') }}"
                                >
                                    ✕
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                @error('attachments')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
                @error('attachments.*')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Form Submit Button --}}
            <div class="pt-4 border-t border-slate-800 flex items-center justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white text-sm font-semibold shadow-xl shadow-indigo-600/30 transition-all flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span wire:loading.remove wire:target="submit">{{ __('Submit Request & Get Live Tracker') }} →</span>
                    <span wire:loading wire:target="submit" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <span>{{ __('Submitting...') }}</span>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
