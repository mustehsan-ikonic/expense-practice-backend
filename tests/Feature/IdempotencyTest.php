<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Problem 3 — duplicate requests / idempotency.
 *
 * Reproduces the duplicate-charge bug on the problems branch: sending the same
 * POST /api/expenses twice with the same Idempotency-Key (what a client retry
 * after a timeout looks like) creates two expenses, because nothing enforces
 * idempotency.
 */
class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_duplicate_expenses_without_idempotency(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(10000)->create();
        $category = Category::factory()->create();

        $payload = [
            'account_id' => $account->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'description' => 'Team lunch',
            'amount' => 50.00,
        ];

        $headers = ['Idempotency-Key' => 'retry-key-123'];

        $first = $this->postJson('/api/expenses', $payload, $headers);
        $second = $this->postJson('/api/expenses', $payload, $headers);

        $first->assertCreated();
        $second->assertCreated();

        // The retry created a SECOND expense — the duplicate charge.
        $this->assertSame(2, Expense::query()->where('description', 'Team lunch')->count());
    }
}
