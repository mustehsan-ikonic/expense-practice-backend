<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Demonstrates Problem 1 (race condition) with REAL parallelism: it resets a
 * demo account to a known balance, then launches many concurrent `practice:spend`
 * OS processes that all debit it at once through the unlocked
 * {@see WalletService}. Because the service reads, checks and
 * writes without a lock, concurrent debits lose updates and the account is
 * overdrawn.
 *
 * This is the "visceral" demo; the guaranteed, deterministic proof lives in
 * RaceConditionTest::it_demonstrates_concurrent_balance_race_condition().
 */
class RaceConditionCommand extends Command
{
    protected $signature = 'practice:race-condition
        {--processes=10 : Number of concurrent debit processes}
        {--amount=200 : Amount each process tries to debit}
        {--balance=1000 : Starting balance for the demo account}';

    protected $description = 'Race many concurrent debits against one account to overspend it (Problem 1)';

    public function handle(): int
    {
        $processes = (int) $this->option('processes');
        $amount = (float) $this->option('amount');
        $balance = (float) $this->option('balance');

        $account = $this->demoAccount($balance);
        $affordable = (int) floor($balance / $amount);

        $this->info("Demo account #{$account->id} starting balance: {$balance}");
        $this->info("Launching {$processes} concurrent debits of {$amount} each.");
        $this->line("Only {$affordable} of them should be able to succeed.");
        $this->newLine();

        $pool = [];
        for ($i = 0; $i < $processes; $i++) {
            $pool[] = Process::path(base_path())->start(
                [PHP_BINARY, base_path('artisan'), 'practice:spend', (string) $account->id, (string) $amount]
            );
        }

        $succeeded = 0;
        foreach ($pool as $invoked) {
            $result = $invoked->wait();
            $this->line('  '.trim($result->output()));
            if ($result->successful()) {
                $succeeded++;
            }
        }

        $account->refresh();
        $finalBalance = (float) $account->balance;
        $expectedBalance = $balance - ($succeeded * $amount);

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Debits that reported success', $succeeded],
            ['Debits that SHOULD fit', $affordable],
            ['Final balance', $finalBalance],
            ['Balance if every success applied', $expectedBalance],
        ]);

        $overspent = $succeeded > $affordable || $finalBalance < 0;
        $lostUpdates = $finalBalance > $expectedBalance;

        if ($overspent || $lostUpdates) {
            $this->error('RACE CONDITION REPRODUCED:');
            if ($overspent) {
                $this->error("  {$succeeded} debits succeeded but only {$affordable} could be afforded — the account was overdrawn.");
            }
            if ($lostUpdates) {
                $this->error("  Final balance {$finalBalance} is higher than {$expectedBalance}: some debits were lost (overwritten).");
            }
            $this->newLine();
            $this->line('On the solutions branch (row locking) this invariant holds and the extra debits are correctly rejected.');

            return self::FAILURE;
        }

        $this->info('No race observed on this run (timing-dependent). Try more --processes; the deterministic proof is in RaceConditionTest.');

        return self::SUCCESS;
    }

    private function demoAccount(float $balance): Account
    {
        $user = User::firstOrCreate(
            ['email' => 'race-demo@example.com'],
            ['name' => 'Race Demo', 'password' => bcrypt('password')],
        );

        $account = Account::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Race Demo Account'],
            ['balance' => $balance, 'currency' => 'USD'],
        );

        $account->update(['balance' => $balance]);

        return $account;
    }
}
