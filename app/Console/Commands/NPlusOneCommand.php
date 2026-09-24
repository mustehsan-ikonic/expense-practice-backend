<?php

namespace App\Console\Commands;

use App\Models\Expense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Demonstrates Problem 2 (N+1 queries). It runs the same access pattern the
 * GET /api/expenses endpoint uses and reports how many SQL queries it took.
 *
 * On the problems branch the relations are lazy-loaded per row, so the count is
 * roughly 1 + 4 * N. On the solutions branch this command eager-loads them and
 * the count collapses to a small constant.
 */
class NPlusOneCommand extends Command
{
    protected $signature = 'practice:n-plus-one {--limit=100 : How many expenses to list}';

    protected $description = 'Count the SQL queries used to list expenses with their relations (Problem 2)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Same pattern as ExpenseController@index on the problems branch:
        // load expenses WITHOUT relations, then touch four relations per row.
        $expenses = Expense::query()->latest('id')->limit($limit)->get();

        $expenses->each(function (Expense $expense): void {
            $expense->user->name;
            $expense->category->name;
            $expense->account->name;
            $expense->participants->count();
        });

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->info("Listed {$expenses->count()} expenses using {$queryCount} SQL queries.");
        $this->newLine();
        $this->line('Problems branch: expect ~1 + 4 x N (a query per relation, per row).');
        $this->line('Solutions branch: expect a small constant (~5) thanks to eager loading.');

        return self::SUCCESS;
    }
}
