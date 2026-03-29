<?php

namespace Database\Factories;

use App\Domains\Automation\Models\AutomationRule;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AutomationRule> */
class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name'         => fake()->words(3, true),
            'active'       => true,
            'trigger'      => fake()->randomElement(['conversation.created', 'conversation.replied', 'conversation.closed']),
            'conditions'   => [],
            'actions'      => [['type' => 'set_priority', 'value' => 'normal']],
        ];
    }
}
