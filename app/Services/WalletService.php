<?php

namespace App\Services;

use App\Exceptions\InsufficientFundsException;
use App\Models\Account;

/**
 * VULNERABLE IMPLEMENTATION (problems branch).
 *
 * debit() performs a read-check-write sequence with NO database transaction and
 * NO row lock. Two requests running concurrently can both read the same starting
 * balance, both pass the funds check, and both write their result — the second
 * write silently overwrites the first (a "lost update"), letting the account be
 * overdrawn. See RaceConditionTest and `php artisan practice:race-condition`.
 *
 * The solutions branch replaces this with a locked transaction.
 */
class WalletService
{
    /**
     * Subtract $amount from the account balance.
     *
     * @throws InsufficientFundsException
     */
    public function debit(Account $account, float $amount): Account
    {
        // READ: fetch the current balance from the database.
        $fresh = Account::findOrFail($account->id);

        // CHECK: make sure there are enough funds.
        if ((float) $fresh->balance < $amount) {
            throw InsufficientFundsException::for($fresh, $amount);
        }

        // (In a real race, a second request reads the SAME balance here, before
        // the first request's write below has landed.)

        // WRITE: compute the new balance in PHP and save it back.
        $fresh->balance = (float) $fresh->balance - $amount;
        $fresh->save();

        return $fresh;
    }
}
