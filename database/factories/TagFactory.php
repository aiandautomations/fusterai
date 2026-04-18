<?php

namespace Database\Factories;

use App\Domains\Conversation\Models\Tag;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tag> */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
