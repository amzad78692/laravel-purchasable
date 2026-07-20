<?php

namespace Amzad\LaravelPurchasable\States\Order;

use Amzad\LaravelPurchasable\States\Transitions\FailedToCancelled;
use Amzad\LaravelPurchasable\States\Transitions\FailedToPending;
use Amzad\LaravelPurchasable\States\Transitions\PaidToPartiallyRefunded;
use Amzad\LaravelPurchasable\States\Transitions\PaidToRefunded;
use Amzad\LaravelPurchasable\States\Transitions\PartiallyRefundedToRefunded;
use Amzad\LaravelPurchasable\States\Transitions\PendingToCancelled;
use Amzad\LaravelPurchasable\States\Transitions\PendingToFailed;
use Amzad\LaravelPurchasable\States\Transitions\PendingToPaid;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class OrderState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, PaidState::class, PendingToPaid::class)
            ->allowTransition(PendingState::class, FailedState::class, PendingToFailed::class)
            ->allowTransition(PendingState::class, CancelledState::class, PendingToCancelled::class)
            ->allowTransition(FailedState::class, CancelledState::class, FailedToCancelled::class)
            ->allowTransition(FailedState::class, PendingState::class, FailedToPending::class)
            ->allowTransition(PaidState::class, RefundedState::class, PaidToRefunded::class)
            ->allowTransition(PaidState::class, PartiallyRefundedState::class, PaidToPartiallyRefunded::class)
            ->allowTransition(PartiallyRefundedState::class, RefundedState::class, PartiallyRefundedToRefunded::class);
    }
}
