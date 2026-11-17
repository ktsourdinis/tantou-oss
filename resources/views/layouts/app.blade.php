<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Tantou (担当) — Human Capacity OS' }}</title>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased selection:bg-indigo-500 selection:text-white">
        {{-- Superadmin Impersonation Banner --}}
        @if (session()->has('impersonator_id'))
            <div class="sticky top-0 z-50 flex flex-wrap items-center justify-between gap-3 bg-amber-500/20 border-b border-amber-500/40 px-4 py-2.5 backdrop-blur-md text-amber-200 text-xs sm:text-sm font-medium shadow-lg">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-500/30 text-amber-300 font-bold text-xs">🛡️</span>
                    <span>
                        {{ __('Superadmin Mode: Viewing as :user (:tenant)', [
                            'user' => auth()->user()?->name ?? 'User',
                            'tenant' => auth()->user()?->tenant?->name ?? 'Tenant'
                        ]) }}
                    </span>
                </div>
                <form method="POST" action="{{ route('superadmin.leave-impersonation') }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-amber-500 text-slate-950 font-semibold hover:bg-amber-400 active:scale-95 transition-all text-xs cursor-pointer shadow-sm">
                        <span>↩</span>
                        <span>{{ __('Return to Superadmin Control Plane') }}</span>
                    </button>
                </form>
            </div>
        @endif

        {{ $slot }}

        <script>
            window.copyToClipboard = window.copyToClipboard || function (text) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                }
                return new Promise(function (resolve, reject) {
                    try {
                        var textArea = document.createElement('textarea');
                        textArea.value = text;
                        textArea.style.position = 'fixed';
                        textArea.style.left = '-999999px';
                        textArea.style.top = '-999999px';
                        textArea.setAttribute('readonly', '');
                        document.body.appendChild(textArea);
                        textArea.focus();
                        textArea.select();
                        var successful = document.execCommand('copy');
                        document.body.removeChild(textArea);
                        if (successful) {
                            resolve();
                        } else {
                            reject(new Error('Copy command failed'));
                        }
                    } catch (err) {
                        reject(err);
                    }
                });
            };
        </script>

        @livewireScripts
    </body>
</html>
