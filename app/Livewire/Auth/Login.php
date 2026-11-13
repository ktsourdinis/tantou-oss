<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Authenticate the user with provided credentials.
     */
    public function authenticate(): mixed
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', __('The provided credentials do not match our records.'));
            $this->password = '';

            return null;
        }

        session()->regenerate();

        return redirect()->intended('/board');
    }

    /**
     * Quick demo one-click login for local environment testing.
     */
    public function quickLogin(string $email): mixed
    {
        if (! app()->environment('local', 'testing')) {
            abort(403, __('Quick demo login is only available in local development.'));
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->addError('email', __('Demo user with email :email was not found. Please re-run seeders.', ['email' => $email]));

            return null;
        }

        Auth::login($user, true);
        session()->regenerate();

        return redirect()->intended('/board');
    }

    /**
     * Render the login component view.
     */
    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
