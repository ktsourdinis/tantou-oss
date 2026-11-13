<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\CapacityBoard;
use App\Livewire\TeamManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Public Landing Page
Route::view('/', 'welcome')->name('landing');

// Guest Authentication Routes
Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

// Authenticated Application Routes
Route::middleware('auth')->group(function (): void {
    Route::get('/board', CapacityBoard::class)->name('board');
    Route::get('/team', TeamManagement::class)->name('team');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    })->name('logout');
});
