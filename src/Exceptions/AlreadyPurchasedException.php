<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Illuminate\Database\Eloquent\Model;

class AlreadyPurchasedException extends PurchasableException
{
    public static function for(Model $buyer, Model $purchasable): self
    {
        return new self(__('Buyer [:buyer] already has a paid or pending order for purchasable [:purchasable].', [
            'buyer' => "{$buyer->getMorphClass()}:{$buyer->getKey()}",
            'purchasable' => "{$purchasable->getMorphClass()}:{$purchasable->getKey()}",
        ]));
    }

    public static function forGuest(string $email, Model $purchasable): self
    {
        return new self(__('Guest [:email] already has a paid or pending order for purchasable [:purchasable].', [
            'email' => $email,
            'purchasable' => "{$purchasable->getMorphClass()}:{$purchasable->getKey()}",
        ]));
    }
}
