<?php

namespace Database\Factories;

use App\Domains\Conversation\Models\CustomView;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomView>
 */
class CustomViewFactory extends Factory
{
    protected $model = CustomView::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => null,
            'name' => $this->faker->words(3, true),
            'color' => $this->faker->hexColor(),
            'filters' => ['status' => 'open'],
            'is_shared' => false,
            'order' => $this->faker->numberBetween(1, 100),
        ];
    }
}
