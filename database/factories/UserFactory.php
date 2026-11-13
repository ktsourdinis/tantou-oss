<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'tenant_id' => null,
            'department_id' => null,
            'role' => 'worker',
            'board_visibility' => 'public',
            'is_available' => true,
            'current_capacity' => 0,
            'max_capacity' => 10,
            'total_points' => 0,
            'locale' => 'en',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user belongs to a specific tenant.
     */
    public function forTenant(?Tenant $tenant = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant?->id ?? Tenant::factory(),
        ]);
    }

    /**
     * Indicate that the user belongs to a specific department.
     */
    public function forDepartment(?Department $department = null): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department?->id ?? Department::factory(),
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'board_visibility' => 'none',
        ]);
    }

    /**
     * Indicate that the user is a coordinator (team lead for department).
     */
    public function coordinator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'coordinator',
            'board_visibility' => 'public',
        ]);
    }

    /**
     * Indicate that the user is a worker.
     */
    public function worker(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'worker',
            'board_visibility' => 'public',
        ]);
    }

    /**
     * Indicate that the user is a client.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'client',
            'board_visibility' => 'none',
        ]);
    }

    /**
     * Indicate that the user is away.
     */
    public function away(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }

    /**
     * Indicate board visibility mode.
     */
    public function withVisibility(string $visibility): static
    {
        return $this->state(fn (array $attributes) => [
            'board_visibility' => $visibility,
        ]);
    }
}
