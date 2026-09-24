<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Happy-path coverage for the expense API. These behaviours are identical on both
 * branches — the problems/solutions difference is in HOW they are implemented, not
 * WHAT the endpoints do.
 */
class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_an_expense_and_debits_the_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(1000)->create();
        $category = Category::factory()->create();

        $response = $this->postJson('/api/expenses', [
            'account_id' => $account->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'description' => 'Groceries',
            'amount' => 120.50,
            'participants' => [$user->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.description', 'Groceries');

        $this->assertSame('879.50', (string) $account->refresh()->balance);
        // The sync test queue runs ProcessExpense immediately, so the expense is
        // already processed by the time the request returns.
        $this->assertDatabaseHas('expenses', [
            'description' => 'Groceries',
            'status' => ExpenseStatus::Processed->value,
        ]);
    }

    #[Test]
    public function it_rejects_an_expense_that_exceeds_the_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(50)->create();
        $category = Category::factory()->create();

        $response = $this->postJson('/api/expenses', [
            'account_id' => $account->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'description' => 'Too expensive',
            'amount' => 500,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame('50.00', (string) $account->refresh()->balance);
    }

    #[Test]
    public function it_validates_the_request(): void
    {
        $this->postJson('/api/expenses', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_id', 'user_id', 'category_id', 'description', 'amount']);
    }
}
