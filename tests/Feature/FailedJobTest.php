<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Exceptions\ExpenseProcessingException;
use App\Jobs\ProcessExpense;
use App\Models\Expense;
use App\Support\FlakyExpenseProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Problem 4 — failed jobs / reliable background processing.
 *
 * Reproduces the fragility of the problems-branch ProcessExpense job:
 *   - a single transient failure is fatal ($tries = 1, no retry), leaving the
 *     expense stuck on "pending" with no summary; and
 *   - handle() is not idempotent, so re-running it duplicates the summary.
 */
class FailedJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_demonstrates_failed_job(): void
    {
        $expense = Expense::factory()->withParticipants(2)->create();
        FlakyExpenseProcessor::scheduleFailures($expense->id, 1);

        $job = new ProcessExpense($expense);

        // The problems-branch job gives up after a single attempt.
        $this->assertSame(1, $job->tries);

        try {
            $job->handle(app(FlakyExpenseProcessor::class));
            $this->fail('Expected the transient failure to throw.');
        } catch (ExpenseProcessingException) {
            // expected: the one and only attempt failed.
        }

        $expense->refresh();

        // Processing was lost: no summary, and the expense is stuck on pending
        // because there is no failed() handler to even mark it failed.
        $this->assertSame(ExpenseStatus::Pending, $expense->status);
        $this->assertDatabaseCount('expense_summaries', 0);
    }

    #[Test]
    public function it_demonstrates_non_idempotent_double_processing(): void
    {
        $expense = Expense::factory()->withParticipants(2)->create();

        // No failures scheduled: both runs succeed.
        $job = new ProcessExpense($expense);
        $job->handle(app(FlakyExpenseProcessor::class));
        $job->handle(app(FlakyExpenseProcessor::class));

        // Re-running the job created a SECOND summary — handle() is not idempotent.
        $this->assertDatabaseCount('expense_summaries', 2);
    }
}
