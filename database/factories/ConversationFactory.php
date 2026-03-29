<?php

namespace Database\Factories;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        $workspace = Workspace::factory()->create();
        return [
            'workspace_id' => $workspace->id,
            'mailbox_id'   => Mailbox::factory()->create(['workspace_id' => $workspace->id])->id,
            'customer_id'  => Customer::factory()->create(['workspace_id' => $workspace->id])->id,
            'subject'      => fake()->sentence(6),
            'status'       => 'open',
            'priority'     => 'normal',
            'channel_type' => 'email',
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => 'open']);
    }

    public function closed(): static
    {
        return $this->state(['status' => 'closed']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function urgent(): static
    {
        return $this->state(['priority' => 'urgent']);
    }
}
