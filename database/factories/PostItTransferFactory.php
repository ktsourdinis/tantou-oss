<?php

namespace Database\Factories;

use App\Models\PostIt;
use App\Models\PostItTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostItTransfer>
 */
class PostItTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_it_id' => PostIt::factory(),
            'actor_id' => User::factory(),
            'from_user_id' => null,
            'to_user_id' => null,
            'action' => 'assigned',
            'created_at' => now(),
        ];
    }
}
