<?php

namespace App\Exceptions;

use App\Support\FlakyExpenseProcessor;
use RuntimeException;

/**
 * Thrown by {@see FlakyExpenseProcessor} to simulate a transient
 * downstream failure (e.g. a flaky report/ledger service) while processing an
 * expense in the background. It carries no external dependency — the failure is
 * entirely local and deterministic so the queue behaviour can be demonstrated.
 */
class ExpenseProcessingException extends RuntimeException
{
    //
}
