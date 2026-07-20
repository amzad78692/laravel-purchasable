<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Illuminate\Database\Eloquent\Model;

class AlreadyPurchasedException extends PurchasableException
{
    public static function for(Model $buyer, Model $purchasable): self
    {
        return new self(sprintf(
            'Buyer [%s:%s] already has a paid or pending order for purchasable [%s:%s].',
            $buyer->getMorphClass(),
            $buyer->getKey(),
            $purchasable->getMorphClass(),
            $purchasable->getKey(),
        ));
    }
}
