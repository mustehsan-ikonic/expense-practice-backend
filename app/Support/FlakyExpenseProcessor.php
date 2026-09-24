<?php

namespace App\Support;

use App\Exceptions\ExpenseProcessingException;
use App\Models\Expense;
use Illuminate\Support\Facades\Cache;

/**
 * Simulates a flaky downstream dependency used while processing an expense in
 * the background (Problem 4). There is NO external service — the failure is
 * local and deterministic:
 *
 *   - A per-expense "remaining failures" counter is stored in the cache.
 *   - Each call to {@see process()} that finds the counter > 0 decrements it and
 *     throws {@see ExpenseProcessingException} (a transient failure).
 *   - Once the counter reaches 0 the call succeeds and returns the summary data.
 *
 * Because the counter lives in the cache (not on the object), it survives across
 * separate job attempts — whether the job is retried by a real queue worker or
 * its handle() is invoked directly in a test.
 */
class FlakyExpenseProcessor
{
    private const PREFIX = 'flaky-expense:';

    /**
     * Compute the data needed to build an expense summary, failing transiently
     * while the scheduled failure counter is still positive.
     *
     * @return array{participant_count: int, per_head_amount: float, total_amount: float}
     */
    public function process(Expense $expense): array
    {
        $key = self::PREFIX.$expense->id;
        $remaining = (int) Cache::get($key, 0);

        if ($remaining > 0) {
            Cache::put($key, $remaining - 1, now()->addDay());

            throw new ExpenseProcessingException(
                "Transient failure while processing expense {$expense->id} ({$remaining} scheduled failure(s) remaining)."
            );
        }

        $participantCount = max($expense->participants()->count(), 1);
        $total = (float) $expense->amount;

        return [
            'participant_count' => $participantCount,
            'per_head_amount' => round($total / $participantCount, 2),
            'total_amount' => $total,
        ];
    }

    /**
     * Schedule the processor to fail the next $times attempts for this expense,
     * then succeed.
     */
    public static function scheduleFailures(int $expenseId, int $times): void
    {
        Cache::put(self::PREFIX.$expenseId, $times, now()->addDay());
    }

    /**
     * Schedule the processor to fail every attempt for this expense, so the job
     * exhausts its retries and lands in failed_jobs.
     */
    public static function alwaysFail(int $expenseId): void
    {
        self::scheduleFailures($expenseId, PHP_INT_MAX);
    }

    /**
     * Forget any scheduled failures for this expense (used to let a retry pass).
     */
    public static function clear(int $expenseId): void
    {
        Cache::forget(self::PREFIX.$expenseId);
    }
}
