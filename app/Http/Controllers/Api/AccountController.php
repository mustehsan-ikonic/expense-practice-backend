<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function show(Account $account): JsonResponse
    {
        return response()->json(['data' => $account]);
    }

    /**
     * List the expenses charged to an account.
     *
     * This endpoint filters expenses by account_id. On the problems branch that
     * column has no index, so MySQL performs a full table scan (Problem 2's
     * "index only where it helps" angle). The solutions branch adds an index on
     * expenses.account_id precisely because of this access pattern.
     */
    public function expenses(Account $account): JsonResponse
    {
        $expenses = Expense::query()
            ->where('account_id', $account->id)
            ->latest('id')
            ->get();

        return response()->json(['data' => $expenses]);
    }
}
