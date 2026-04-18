<?php

namespace Database\Factories;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Thread> */
class ThreadFactory extends Factory
{
    protected $model = Thread::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'type' => 'message',
            'body' => '<p>'.fake()->paragraph().'</p>',
            'source' => 'email',
            'status' => 'received',
        ];
    }

    public function note(): static
    {
        return $this->state(['type' => 'note', 'status' => 'note']);
    }

    public function fromCustomer(): static
    {
        return $this->state(['user_id' => null]);
    }
}
