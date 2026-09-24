<?php

namespace App\Exceptions;

use App\Models\Account;
use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    public function __construct(
        public readonly int $accountId,
        public readonly float $available,
        public readonly float $requested,
    ) {
        parent::__construct(
            "Account {$accountId} has {$available} available but {$requested} was requested."
        );
    }

    public static function for(Account $account, float $requested): self
    {
        return new self((int) $account->id, (float) $account->balance, $requested);
    }
}
