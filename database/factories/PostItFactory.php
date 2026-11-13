<?php

namespace Database\Factories;

use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostIt>
 */
class PostItFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'department_id' => null,
            'requester_id' => User::factory(),
            'assignee_id' => null,
            'helper_id' => null,
            'helper_split' => 0,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => 'backlog',
            'priority' => 'normal',
            'weight' => 1,
            'bounty_points' => 10,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the post-it is currently being worked on.
     */
    public function doing(?User $assignee = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'doing',
            'assignee_id' => $assignee?->id ?? User::factory(),
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the post-it is completed.
     */
    public function done(?User $assignee = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
            'assignee_id' => $assignee?->id ?? User::factory(),
            'bounty_points' => fake()->numberBetween(10, 80),
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the post-it is co-op assisted.
     */
    public function assisted(?User $helper = null, int $split = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'helper_id' => $helper?->id ?? User::factory(),
            'helper_split' => min(50, max(0, $split)),
        ]);
    }
}
