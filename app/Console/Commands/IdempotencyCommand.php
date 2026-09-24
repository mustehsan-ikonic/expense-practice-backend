<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Demonstrates Problem 3 (duplicate requests). It sends the SAME POST
 * /api/expenses twice with the same Idempotency-Key header — exactly what a
 * client retry after a network timeout looks like — through the full HTTP
 * middleware stack, then counts how many expenses were actually created.
 *
 * Problems branch: 2 expenses (the retry duplicated the charge).
 * Solutions branch: 1 expense (the idempotency middleware replayed the first
 * response instead of creating a second).
 */
class IdempotencyCommand extends Command
{
    protected $signature = 'practice:idempotency';

    protected $description = 'Send a retried POST /api/expenses with a repeated Idempotency-Key (Problem 3)';

    public function handle(Kernel $kernel): int
    {
        [$user, $account, $category] = $this->demoContext();

        $key = (string) Str::uuid();
        $description = 'Idempotency demo '.Str::uuid();

        $payload = [
            'account_id' => $account->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'description' => $description,
            'amount' => 25.00,
        ];

        $this->info("Idempotency-Key: {$key}");
        $this->line('Sending the identical request twice (simulating a retried timeout)...');
        $this->newLine();

        foreach ([1, 2] as $attempt) {
            $request = Request::create('/api/expenses', 'POST', $payload, server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => $key,
            ]);

            $response = $kernel->handle($request);
            $this->line("  request #{$attempt} -> HTTP {$response->getStatusCode()}");
        }

        $created = Expense::query()->where('description', $description)->count();

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Requests sent', 2],
            ['Expenses created', $created],
        ]);

        if ($created > 1) {
            $this->error("DUPLICATE REPRODUCED: {$created} expenses created from one logical request.");
            $this->line('On the solutions branch the Idempotency-Key makes this exactly 1.');

            return self::FAILURE;
        }

        $this->info('Exactly one expense created — the retry was de-duplicated.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: User, 1: Account, 2: Category}
     */
    private function demoContext(): array
    {
        $user = User::firstOrCreate(
            ['email' => 'idempotency-demo@example.com'],
            ['name' => 'Idempotency Demo', 'password' => bcrypt('password')],
        );

        $account = Account::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Idempotency Demo Account'],
            ['balance' => 100000, 'currency' => 'USD'],
        );

        $account->update(['balance' => 100000]);

        $category = Category::firstOrCreate(
            ['slug' => 'idempotency-demo'],
            ['name' => 'Idempotency Demo'],
        );

        return [$user, $account, $category];
    }
}
