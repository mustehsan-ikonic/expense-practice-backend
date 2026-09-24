<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\WalletService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MySqlTestCase;

/**
 * Problem 1 — race condition on the account balance.
 *
 * Reproduces a lost update deterministically using TWO real MySQL connections
 * interleaved by hand. This mirrors exactly what {@see WalletService::debit()}
 * does on the problems branch — find, check, mutate in PHP, save — but forces the
 * interleaving that happens by chance under real concurrent load:
 *
 *   1. A reads balance 1000
 *   2. B reads balance 1000
 *   3. A checks 1000 >= 700, writes 300
 *   4. B checks 1000 >= 500, writes 500   <-- overwrites A, "losing" the 700 debit
 *
 * Both debits report success, yet the account ends at 500 as if only the second
 * happened. Total "spent" is 1200 against a 1000 balance — the account is
 * overdrawn. There is no row lock to serialise the two, so the update is lost.
 *
 * @group mysql
 */
#[Group('mysql')]
class RaceConditionTest extends MySqlTestCase
{
    #[Test]
    public function it_demonstrates_concurrent_balance_race_condition(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(1000)->create();

        // A second connection to the same test database = a second DB session,
        // so the two reads below do not see each other's uncommitted writes.
        config(['database.connections.mysql_test_2' => config('database.connections.mysql_test')]);

        $connA = 'mysql_test';
        $connB = 'mysql_test_2';

        // (1) & (2): both sessions read the SAME starting balance.
        $a = Account::on($connA)->findOrFail($account->id);
        $b = Account::on($connB)->findOrFail($account->id);

        // (3): A debits 700 (1000 >= 700) and saves 300.
        $this->assertGreaterThanOrEqual(700, (float) $a->balance);
        $a->balance = (float) $a->balance - 700;
        $a->save();

        // (4): B debits 500 using its STALE read of 1000, saving 500 and
        // clobbering A's write.
        $this->assertGreaterThanOrEqual(500, (float) $b->balance);
        $b->balance = (float) $b->balance - 500;
        $b->save();

        $finalBalance = (float) Account::on($connA)->findOrFail($account->id)->balance;

        // The lost-update fingerprint: only B's write survived.
        $this->assertEqualsWithDelta(500.0, $finalBalance, 0.001);

        // A correctly serialised system would have rejected one debit and left
        // 300; instead 700 + 500 = 1200 was "spent" from a 1000 balance.
        $this->assertGreaterThan(300.0, $finalBalance, 'The 700 debit was lost, so more money remains than a serialised run would leave.');
    }
}
