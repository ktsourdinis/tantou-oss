<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'color' => '#6366f1',
        ];
    }

    /**
     * Indicate that the department belongs to a specific tenant.
     */
    public function forTenant(?Tenant $tenant = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant?->id ?? Tenant::factory(),
        ]);
    }
}
