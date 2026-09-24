<?php

namespace App\Console\Commands;

use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use App\Services\WalletService;
use Illuminate\Console\Command;

/**
 * Child process for the race-condition demo. It debits a single account through
 * the real {@see WalletService} and reports the outcome via its exit code
 * (0 = debited, 1 = rejected). {@see RaceConditionCommand} launches many of
 * these at once to race them against the same account balance.
 */
class SpendCommand extends Command
{
    protected $signature = 'practice:spend {account : Account id} {amount : Amount to debit}';

    protected $description = 'Debit an account once (used as a child process by practice:race-condition)';

    public function handle(WalletService $wallet): int
    {
        $account = Account::findOrFail((int) $this->argument('account'));
        $amount = (float) $this->argument('amount');

        try {
            $updated = $wallet->debit($account, $amount);
            $this->line("OK      debited {$amount}; balance now {$updated->balance}");

            return self::SUCCESS;
        } catch (InsufficientFundsException $e) {
            $this->line("REJECT  {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
