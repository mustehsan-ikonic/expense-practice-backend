<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientFundsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    /**
     * List expenses.
     *
     * VULNERABLE (problems branch): the expenses are loaded WITHOUT their
     * relations, then the map below reaches for $e->user, $e->category,
     * $e->account and $e->participants per row. Each of those accessors runs a
     * fresh query, so listing N expenses costs 1 + 4N queries — the N+1 problem
     * (Problem 2). See NPlusOneTest and `php artisan practice:n-plus-one`.
     */
    public function index(): JsonResponse
    {
        $expenses = Expense::query()->latest('id')->get();

        $data = $expenses->map(fn (Expense $expense): array => [
            'id' => $expense->id,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'status' => $expense->status,
            'user' => $expense->user->name,
            'category' => $expense->category->name,
            'account' => $expense->account->name,
            'participant_count' => $expense->participants->count(),
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Create an expense.
     *
     * VULNERABLE (problems branch): there is no idempotency guard, so a client
     * that retries after a timeout (same Idempotency-Key header) creates a
     * second expense (Problem 3), and the debit inside the service is racy
     * (Problem 1).
     */
    public function store(StoreExpenseRequest $request, ExpenseService $service): JsonResponse
    {
        try {
            $expense = $service->create($request->toServiceData());
        } catch (InsufficientFundsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $expense->load('participants')], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $expense->load(['user', 'category', 'account', 'participants', 'summary']);

        return response()->json(['data' => $expense]);
    }
}
