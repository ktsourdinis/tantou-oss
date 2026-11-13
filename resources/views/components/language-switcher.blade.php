<div {{ $attributes->merge(['class' => 'inline-flex items-center p-0.5 rounded-xl bg-slate-900/90 border border-slate-800 text-xs font-semibold shadow-inner']) }}>
    <form method="POST" action="{{ route('locale.switch', 'en') }}" class="inline">
        @csrf
        <button
            type="submit"
            class="px-2 py-1 rounded-lg transition-all cursor-pointer {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
            title="English"
        >
            EN
        </button>
    </form>
    <span class="text-slate-700 text-[10px] mx-0.5">|</span>
    <form method="POST" action="{{ route('locale.switch', 'el') }}" class="inline">
        @csrf
        <button
            type="submit"
            class="px-2 py-1 rounded-lg transition-all cursor-pointer {{ app()->getLocale() === 'el' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
            title="Ελληνικά"
        >
            ΕΛ
        </button>
    </form>
</div>
