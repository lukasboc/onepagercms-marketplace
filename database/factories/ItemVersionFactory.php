<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemVersionFactory extends Factory
{
    protected $model = ItemVersion::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'version' => '1.0.0',
            'zip_path' => null,
            'changelog' => 'Initial release.',
            'requires_opcms' => '1.2.0',
            'requires_php' => '7.4',
            'status' => ItemVersion::STATUS_APPROVED,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => ItemVersion::STATUS_PENDING]);
    }
}
