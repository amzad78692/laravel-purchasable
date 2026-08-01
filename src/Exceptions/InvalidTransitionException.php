<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

class InvalidTransitionException extends PurchasableException
{
    public static function from(string $fromState, string $toState, ?CouldNotPerformTransition $previous = null): self
    {
        return new self(
            __('Cannot transition order status from [:from] to [:to].', ['from' => $fromState, 'to' => $toState]),
            previous: $previous,
        );
    }
}
