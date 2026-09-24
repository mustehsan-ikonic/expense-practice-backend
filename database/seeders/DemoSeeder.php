<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a realistic dataset: several users, one account each, six categories and
 * ~120 expenses (each with 2-4 split participants). The volume is deliberate — it
 * makes the N+1 query problem (Problem 2) obvious when listing expenses.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const USERS = 8;

    private const EXPENSES = 120;

    public function run(): void
    {
        $categories = collect(['Groceries', 'Transport', 'Dining', 'Utilities', 'Entertainment', 'Travel'])
            ->map(fn (string $name) => Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            ));

        $users = User::factory()->count(self::USERS)->create();

        $accounts = $users->map(fn (User $user) => Account::factory()->withBalance(5000)->for($user)->create());

        foreach (range(1, self::EXPENSES) as $ignored) {
            Expense::factory()
                ->withParticipants(fake()->numberBetween(2, 4))
                ->create([
                    'user_id' => $users->random()->id,
                    'account_id' => $accounts->random()->id,
                    'category_id' => $categories->random()->id,
                ]);
        }
    }
}
