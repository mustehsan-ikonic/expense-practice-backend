<?php

namespace App\Jobs;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseSummary;
use App\Support\FlakyExpenseProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * VULNERABLE IMPLEMENTATION (problems branch).
 *
 * The "secondary operation" for an expense: compute the per-head split and store
 * an {@see ExpenseSummary}. It fails in three ways that Problem 4 is about:
 *
 *   - $tries = 1 and no backoff(): a single transient failure (see
 *     {@see FlakyExpenseProcessor}) permanently loses the processing.
 *   - no failed() handler: when the job dies the expense is left stuck on
 *     "pending" with no record of why.
 *   - handle() is NOT idempotent: it blindly inserts a new summary row, so any
 *     re-run (e.g. a manual queue:retry) produces duplicate summaries.
 *
 * The solutions branch adds retries + backoff, a failed() handler, and makes
 * handle() idempotent.
 */
class ProcessExpense implements ShouldQueue
{
    use Queueable;

    /**
     * No retries: one failure and the job is gone.
     */
    public int $tries = 1;

    public function __construct(public readonly Expense $expense) {}

    public function handle(FlakyExpenseProcessor $processor): void
    {
        // May throw a transient ExpenseProcessingException. With $tries = 1 there
        // is no second attempt, so a transient blip is fatal.
        $data = $processor->process($this->expense);

        // Non-idempotent: always INSERTs. Re-running the job duplicates the row.
        ExpenseSummary::create([
            'expense_id' => $this->expense->id,
            'participant_count' => $data['participant_count'],
            'per_head_amount' => $data['per_head_amount'],
            'total_amount' => $data['total_amount'],
        ]);

        $this->expense->update([
            'status' => ExpenseStatus::Processed,
            'processed_at' => now(),
        ]);
    }
}
