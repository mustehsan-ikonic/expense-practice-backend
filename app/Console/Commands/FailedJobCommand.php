<?php

namespace App\Console\Commands;

use App\Jobs\ProcessExpense;
use App\Models\Expense;
use App\Models\ExpenseSummary;
use App\Support\FlakyExpenseProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Demonstrates Problem 4 (failed jobs / reliable background processing) against
 * a REAL database queue driven by an in-process `queue:work --once` worker.
 *
 * The same command runs on both branches; the OUTCOME is what differs:
 *
 *   Scenario 1 (transient failure):
 *     - problems: $tries = 1, so the first blip sends the job to failed_jobs and
 *       the expense is stuck on "pending" (no failed() handler, no summary).
 *     - solutions: retries with backoff let attempt #3 succeed; one summary.
 *
 *   Scenario 2 (permanent failure + recovery):
 *     - the job always fails and lands in failed_jobs; solutions also marks the
 *       expense "failed" via failed(). Then `queue:retry` re-runs it and (with
 *       the failures cleared) it recovers — idempotently on solutions.
 *
 * It runs on a dedicated queue so it never touches your other queued jobs.
 */
class FailedJobCommand extends Command
{
    protected $signature = 'practice:failed-job';

    protected $description = 'Drive a flaky ProcessExpense job through a real queue worker (Problem 4)';

    private const QUEUE = 'practice-failed-job';

    public function handle(): int
    {
        $this->transientScenario();
        $this->newLine();
        $this->permanentThenRetryScenario();

        return self::SUCCESS;
    }

    private function transientScenario(): void
    {
        $this->info('=== Scenario 1: transient failure ===');
        $this->resetDemoQueue();

        $expense = $this->freshExpense();
        FlakyExpenseProcessor::scheduleFailures($expense->id, 2);
        ProcessExpense::dispatch($expense)->onConnection('database')->onQueue(self::QUEUE);

        $this->drainQueue($expense, maxAttempts: 6);

        $expense->refresh();
        $this->reportState($expense);
        $this->line($expense->summary()->exists()
            ? 'Result: the retries absorbed the transient failures and the summary was built.'
            : 'Result: the transient failure was fatal — no retry, no summary, expense left pending.');
    }

    private function permanentThenRetryScenario(): void
    {
        $this->info('=== Scenario 2: permanent failure, then queue:retry recovery ===');
        $this->resetDemoQueue();

        $expense = $this->freshExpense();
        FlakyExpenseProcessor::alwaysFail($expense->id);
        ProcessExpense::dispatch($expense)->onConnection('database')->onQueue(self::QUEUE);

        $this->drainQueue($expense, maxAttempts: 6);
        $expense->refresh();

        $failed = DB::table('failed_jobs')->where('queue', self::QUEUE)->get();
        $this->line("failed_jobs rows for this demo: {$failed->count()}");
        $this->reportState($expense);

        if ($failed->isEmpty()) {
            $this->warn('No failed job was recorded — nothing to retry.');

            return;
        }

        $this->newLine();
        $this->line('Recovering: clearing the injected failures and running `queue:retry`...');
        FlakyExpenseProcessor::clear($expense->id);
        Artisan::call('queue:retry', ['id' => $failed->pluck('uuid')->all()]);

        $this->drainQueue($expense, maxAttempts: 3);
        $expense->refresh();

        $this->reportState($expense);
        $this->line($expense->summary()->exists()
            ? 'Result: the previously-failed job was retried and recovered.'
            : 'Result: still not recovered.');
    }

    /**
     * Run `queue:work --once` repeatedly until the job leaves the queue, resetting
     * availability each time so backoff delays do not slow the demo down.
     */
    private function drainQueue(Expense $expense, int $maxAttempts): void
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if (DB::table('jobs')->where('queue', self::QUEUE)->count() === 0) {
                return;
            }

            DB::table('jobs')->where('queue', self::QUEUE)->update(['available_at' => now()->timestamp]);

            Artisan::call('queue:work', [
                'connection' => 'database',
                '--queue' => self::QUEUE,
                '--once' => true,
            ]);

            $summaries = ExpenseSummary::query()->where('expense_id', $expense->id)->count();
            $failed = DB::table('failed_jobs')->where('queue', self::QUEUE)->count();
            $this->line("  attempt {$attempt}: summaries={$summaries} failed_jobs={$failed}");
        }
    }

    private function reportState(Expense $expense): void
    {
        $this->table(['Field', 'Value'], [
            ['expense status', $expense->status->value],
            ['summaries', $expense->summary()->count()],
            ['failed_jobs (this demo)', DB::table('failed_jobs')->where('queue', self::QUEUE)->count()],
        ]);
    }

    private function freshExpense(): Expense
    {
        return Expense::factory()->withParticipants(2)->create();
    }

    private function resetDemoQueue(): void
    {
        DB::table('jobs')->where('queue', self::QUEUE)->delete();
        DB::table('failed_jobs')->where('queue', self::QUEUE)->delete();
    }
}
