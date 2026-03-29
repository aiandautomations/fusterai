<?php

namespace Database\Factories;

use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mailbox> */
class MailboxFactory extends Factory
{
    protected $model = Mailbox::class;

    public function definition(): array
    {
        return [
            'workspace_id'  => Workspace::factory(),
            'name'          => fake()->company() . ' Support',
            'email'         => fake()->unique()->safeEmail(),
            'active'        => true,
            'channel_type'  => 'email',
            'webhook_token' => bin2hex(random_bytes(16)),
        ];
    }
}
