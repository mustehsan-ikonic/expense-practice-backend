<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Jobs\ProcessExpense;
use App\Models\Account;
use App\Models\Expense;

/**
 * VULNERABLE IMPLEMENTATION (problems branch).
 *
 * create() debits the account, records the expense with its split participants,
 * and dispatches the background job that builds the summary. On this branch the
 * work is NOT wrapped in a database transaction and NOTHING enforces
 * idempotency, so:
 *
 *   - a retried POST (Problem 3) creates a second, duplicate expense, and
 *   - the debit uses the racy {@see WalletService::debit()} (Problem 1).
 *
 * The solutions branch wraps debit + insert in one locked transaction and guards
 * the endpoint with an idempotency key.
 */
class ExpenseService
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * @param  array{
     *     account_id: int,
     *     user_id: int,
     *     category_id: int,
     *     description: string,
     *     amount: float,
     *     participants?: array<int, int>,
     *     idempotency_key?: string|null,
     * }  $data
     */
    public function create(array $data): Expense
    {
        $account = Account::findOrFail($data['account_id']);

        // Problem 1 lives here: unlocked, non-transactional debit.
        $this->wallet->debit($account, (float) $data['amount']);

        $expense = Expense::create([
            'account_id' => $account->id,
            'user_id' => $data['user_id'],
            'category_id' => $data['category_id'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'status' => ExpenseStatus::Pending,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);

        $this->attachParticipants($expense, $data['participants'] ?? []);

        // Problem 4 lives in the job this dispatches.
        ProcessExpense::dispatch($expense);

        return $expense;
    }

    /**
     * Split the expense amount evenly between the given participant user ids. If
     * none are supplied, the payer is the sole participant.
     *
     * @param  array<int, int>  $participantUserIds
     */
    private function attachParticipants(Expense $expense, array $participantUserIds): void
    {
        $userIds = $participantUserIds !== [] ? $participantUserIds : [$expense->user_id];
        $share = round((float) $expense->amount / count($userIds), 2);

        foreach ($userIds as $userId) {
            $expense->participants()->create([
                'user_id' => $userId,
                'share_amount' => $share,
            ]);
        }
    }
}
