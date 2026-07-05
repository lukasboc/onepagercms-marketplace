<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'plugin',
            'slug' => 'item-' . fake()->unique()->numberBetween(100, 999999),
            'name' => ucwords(fake()->words(2, true)),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'is_paid' => false,
            'purchase_url' => null,
            'status' => Item::STATUS_APPROVED,
            'downloads' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => Item::STATUS_PENDING]);
    }

    public function paid(): static
    {
        return $this->state([
            'is_paid' => true,
            'purchase_url' => 'https://developer.example/buy',
        ]);
    }

    public function theme(): static
    {
        return $this->state(['type' => 'theme']);
    }
}
