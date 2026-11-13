<?php

namespace App\Livewire\Auth;

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class Register extends Component
{
    #[Validate('required|string|min:2|max:100')]
    public string $organizationName = '';

    #[Validate('required|string|min:2|max:100')]
    public string $name = '';

    #[Validate('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Register a new workspace tenant, department, and admin user.
     */
    public function register(): mixed
    {
        $this->validate();

        // Generate unique slug
        $baseSlug = Str::slug($this->organizationName);
        if ($baseSlug === '') {
            $baseSlug = 'workspace';
        }

        $slug = $baseSlug;
        $counter = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        // 1. Create Tenant Organization
        $tenant = Tenant::create([
            'name' => $this->organizationName,
            'slug' => $slug,
            'settings' => [
                'theme' => 'dark',
                'timezone' => 'UTC',
            ],
        ]);

        // 2. Create Default Department
        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'General',
            'slug' => 'general',
            'color' => '#6366f1',
        ]);

        // 3. Create Tenant Admin User
        $user = User::create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => 'admin',
            'current_capacity' => 0,
            'max_capacity' => 10,
            'total_points' => 0,
        ]);

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('board');
    }

    /**
     * Render the registration component view.
     */
    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
