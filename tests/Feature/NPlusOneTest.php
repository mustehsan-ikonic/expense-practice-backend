<?php

namespace Tests\Feature;

use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Problem 2 — N+1 queries.
 *
 * Reproduces the N+1 explosion on the problems branch: listing expenses lazy-
 * loads four relations per row, so the query count grows with the number of
 * rows instead of staying constant.
 */
class NPlusOneTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_demonstrates_n_plus_one_queries(): void
    {
        $count = 25;
        Expense::factory()->count($count)->withParticipants(2)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/expenses');

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()->assertJsonCount($count, 'data');

        // The vulnerable endpoint runs one base query plus four per expense
        // (user, category, account, participants). The exact number is 1 + 4N,
        // but asserting it clearly outgrows the row count is enough to prove the
        // N+1: a correct implementation uses a small constant (~5) regardless.
        $this->assertGreaterThan($count * 3, $queries, 'Expected the query count to scale with the number of rows.');
    }
}
