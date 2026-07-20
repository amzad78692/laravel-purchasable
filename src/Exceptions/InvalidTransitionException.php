<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

class InvalidTransitionException extends PurchasableException
{
    public static function from(string $fromState, string $toState, ?CouldNotPerformTransition $previous = null): self
    {
        return new self(
            "Cannot transition order status from [{$fromState}] to [{$toState}].",
            previous: $previous,
        );
    }
}
