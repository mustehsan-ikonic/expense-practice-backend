<?php

namespace App\Http\Requests;

use App\Services\ExpenseService;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'participants' => ['sometimes', 'array'],
            'participants.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    /**
     * Shape the validated input (plus the Idempotency-Key header) into the array
     * consumed by {@see ExpenseService::create()}.
     *
     * @return array{
     *     account_id: int,
     *     user_id: int,
     *     category_id: int,
     *     description: string,
     *     amount: float,
     *     participants: array<int, int>,
     *     idempotency_key: string|null,
     * }
     */
    public function toServiceData(): array
    {
        return [
            'account_id' => (int) $this->integer('account_id'),
            'user_id' => (int) $this->integer('user_id'),
            'category_id' => (int) $this->integer('category_id'),
            'description' => (string) $this->string('description'),
            'amount' => (float) $this->input('amount'),
            'participants' => array_map('intval', $this->input('participants', [])),
            'idempotency_key' => $this->header('Idempotency-Key'),
        ];
    }
}
