<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CannedResponse>
 */
class CannedResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'mailbox_id'   => null,
            'name'         => $this->faker->sentence(3),
            'content'      => '<p>' . $this->faker->paragraph() . '</p>',
        ];
    }
}
