<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ExpenseSummary>
 */
class ExpenseSummaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $count = fake()->numberBetween(1, 5);
        $total = fake()->randomFloat(2, 10, 500);

        return [
            'expense_id' => Expense::factory(),
            'participant_count' => $count,
            'per_head_amount' => round($total / $count, 2),
            'total_amount' => $total,
        ];
    }
}
