<?php

namespace Amzad\LaravelPurchasable\Exceptions;

class GuestCheckoutException extends PurchasableException
{
    public static function disabled(): self
    {
        return new self(__('Guest checkout is disabled. Enable purchasable.allow_guest_checkout to create orders without a buyer.'));
    }

    public static function invalidEmail(): self
    {
        return new self(__('Guest checkout requires a valid email address.'));
    }
}
