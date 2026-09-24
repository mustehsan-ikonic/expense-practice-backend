<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Checking', 'Savings', 'Cash Wallet', 'Credit Line']).' '.fake()->numberBetween(1, 99),
            'balance' => fake()->randomFloat(2, 100, 10000),
            'currency' => 'USD',
        ];
    }

    /**
     * Give the account a precise starting balance (used by the race-condition demo).
     */
    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
        ]);
    }
}
