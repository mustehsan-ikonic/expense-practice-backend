<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 5, 500),
            'status' => ExpenseStatus::Pending,
            'idempotency_key' => null,
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::Processed,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::Failed,
        ]);
    }

    /**
     * Attach a set of split participants whose shares add up to the expense amount.
     */
    public function withParticipants(int $count = 3): static
    {
        return $this->afterCreating(function (Expense $expense) use ($count): void {
            $participants = User::query()->inRandomOrder()->limit($count)->get();

            while ($participants->count() < $count) {
                $participants->push(User::factory()->create());
            }

            $share = round(((float) $expense->amount) / max($count, 1), 2);

            foreach ($participants as $user) {
                ExpenseParticipant::factory()->create([
                    'expense_id' => $expense->id,
                    'user_id' => $user->id,
                    'share_amount' => $share,
                ]);
            }
        });
    }
}
