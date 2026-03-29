<?php

namespace Database\Factories;

use App\Domains\Customer\Models\Customer;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name'         => fake()->name(),
            'email'        => fake()->unique()->safeEmail(),
            'phone'        => fake()->optional()->phoneNumber(),
        ];
    }
}
