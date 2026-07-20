<?php

namespace Amzad\LaravelPurchasable\Events;

use Amzad\LaravelPurchasable\Models\Transaction;

class TransactionFailed
{
    public function __construct(public readonly Transaction $transaction) {}
}
