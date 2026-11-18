<?php

use App\Http\Controllers\AttachmentController;
use App\Livewire\Auth\CompleteRegistration;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\RegisterTenant;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\CapacityBoard;
use App\Livewire\PublicSubmitRequest;
use App\Livewire\PublicTrackRequest;
use App\Livewire\SuperadminDashboard;
use App\Livewire\TeamManagement;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Public Landing Page
Route::view('/', 'welcome')->name('landing');

// Public Guest Drop-Box & Live Request Tracking Routes
Route::get('/submit/{tenantSlug?}', PublicSubmitRequest::class)->name('public.submit')->middleware('throttle:60,1');
Route::get('/track/{token}', PublicTrackRequest::class)->name('public.track')->middleware('throttle:120,1');

// Locale Switcher Route
Route::post('/locale/{locale}', function (Request $request, string $locale) {
    if (! in_array($locale, ['en', 'el'], true)) {
        abort(400);
    }

    $request->session()->put('locale', $locale);

    if ($request->user()) {
        $request->user()->update(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

// Guest Authentication Routes
Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
    Route::match(['get', 'post'], '/demo-login/{email?}', function (Request $request, ?string $email = null) {
        if (! Login::isDemoLoginEnabled()) {
            abort(403, __('Quick demo login is only available in Demo Mode or local development.'));
        }

        if ($request->filled('user_id') || $request->filled('id')) {
            abort(403, __('Arbitrary user ID login is forbidden.'));
        }

        $targetEmail = strtolower(trim((string) ($email ?? $request->input('email', ''))));

        if (! Login::isDemoEmail($targetEmail)) {
            abort(403, __('Only explicit demo accounts (@tantou.local) are permitted for quick demo login.'));
        }

        $user = User::where('email', $targetEmail)->first();

        if (! $user) {
            return redirect()->route('login')->with('error', __('Demo user with email :email was not found. Please re-run seeders.', ['email' => $targetEmail]));
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        if ($user->tenant) {
            $request->session()->put('active_tenant_id', $user->tenant->id);
        }

        return redirect()->intended('/board');
    })->withoutMiddleware(ValidateCsrfToken::class)->name('demo.login');
    Route::get('/register', RegisterTenant::class)->name('register');
    Route::get('/register/complete/{token}', CompleteRegistration::class)->name('register.complete');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

// Authenticated Application Routes
Route::middleware('auth')->group(function (): void {
    Route::get('/board', CapacityBoard::class)->name('board');
    Route::get('/team', TeamManagement::class)->name('team');

    // Superadmin Platform Control Plane
    Route::get('/superadmin', SuperadminDashboard::class)
        ->name('superadmin.dashboard')
        ->middleware('superadmin');

    // Superadmin Impersonation Exit Route
    Route::post('/superadmin/leave-impersonation', function (Request $request) {
        if (! $request->session()->has('impersonator_id')) {
            return redirect()->route('board');
        }

        $impersonatorId = $request->session()->pull('impersonator_id');
        $request->session()->forget('active_tenant_id');

        $originalUser = User::withoutGlobalScopes()->find($impersonatorId);
        if ($originalUser && $originalUser->is_superadmin) {
            Auth::login($originalUser);

            return redirect()->route('superadmin.dashboard');
        }

        Auth::logout();

        return redirect()->route('login');
    })->name('superadmin.leave-impersonation');

    // Secure attachments handling
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    })->name('logout');
});
